<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockBatch;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(private AlertService $alerts, private AuditService $audit) {}

    public function addBatch(Product $product, string $batchNumber, string $productionDate, float $quantity, User $user): StockBatch
    {
        return DB::transaction(function () use ($product, $batchNumber, $productionDate, $quantity, $user) {
            $product->loadMissing('subsidiary');
            if (! $product->subsidiary->tracks_expiry) {
                throw ValidationException::withMessages(['batch' => 'Cette filiale ne nécessite pas de suivi par lot.']);
            }

            $production = Carbon::parse($productionDate)->startOfDay();
            $months = $product->subsidiary->default_shelf_life_months ?: config('ks.sossoguinai_shelf_life_months', 6);
            $expiry = $production->copy()->addMonthsNoOverflow($months);

            $batch = StockBatch::create([
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'production_date' => $production->toDateString(),
                'expiry_date' => $expiry->toDateString(),
                'quantity_on_hand' => $quantity,
                'reserved_quantity' => 0,
            ]);

            $stock = StockItem::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $before = (float) $stock->quantity_on_hand;
            $stock->quantity_on_hand = $before + $quantity;
            $stock->save();

            $this->movement($product, $batch, StockMovementType::IN, $quantity, $before, (float) $stock->quantity_on_hand, 'Production / entrée lot '.$batchNumber, $user);
            $this->alerts->refreshProduct($product->fresh(['stockItem', 'subsidiary']));
            $this->audit->log('stock.batch_added', $batch, $product->subsidiary, ['quantity' => $quantity]);

            return $batch;
        });
    }

    public function adjust(Product $product, StockMovementType $type, float $quantity, string $reason, User $user): void
    {
        DB::transaction(function () use ($product, $type, $quantity, $reason, $user) {
            $product->loadMissing('subsidiary');
            $stock = StockItem::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $before = (float) $stock->quantity_on_hand;

            if (in_array($type, [StockMovementType::IN, StockMovementType::ADJUSTMENT_IN], true)) {
                if ($product->subsidiary->tracks_expiry) {
                    throw ValidationException::withMessages(['quantity' => 'Pour cette filiale, utilisez une entrée par lot avec date de production.']);
                }
                $stock->quantity_on_hand = $before + $quantity;
                $stock->save();
                $this->movement($product, null, $type, $quantity, $before, (float) $stock->quantity_on_hand, $reason, $user);
            } else {
                $available = $before - (float) $stock->reserved_quantity;
                if ($quantity > $available) {
                    throw ValidationException::withMessages(['quantity' => "Stock disponible insuffisant ({$available})."]);
                }

                if ($product->subsidiary->tracks_expiry) {
                    $this->consumeUnreservedBatches($product, $quantity, $reason, $user, $stock);
                } else {
                    $stock->quantity_on_hand = $before - $quantity;
                    $stock->save();
                    $this->movement($product, null, $type, $quantity, $before, (float) $stock->quantity_on_hand, $reason, $user);
                }
            }

            $this->alerts->refreshProduct($product->fresh(['stockItem', 'subsidiary']));
            $this->audit->log('stock.adjusted', $product, $product->subsidiary, ['type' => $type->value, 'quantity' => $quantity, 'reason' => $reason]);
        });
    }

    public function reserveSale(Sale $sale, User $user): void
    {
        DB::transaction(function () use ($sale, $user) {
            $sale->load('lines.product.subsidiary');

            foreach ($sale->lines as $line) {
                $this->reserveLine($line, $user);
            }

            foreach ($sale->lines as $line) {
                $this->alerts->refreshProduct($line->product->fresh(['stockItem', 'subsidiary']));
            }
        });
    }

    public function deliverSale(Sale $sale, User $user): void
    {
        DB::transaction(function () use ($sale, $user) {
            $sale->load('lines.product');

            foreach ($sale->lines as $line) {
                $reservations = StockReservation::query()
                    ->where('sale_line_id', $line->id)
                    ->where('status', ReservationStatus::ACTIVE->value)
                    ->lockForUpdate()
                    ->get();

                if ($reservations->isEmpty()) {
                    throw ValidationException::withMessages(['sale' => 'Réservation de stock introuvable pour la commande.']);
                }

                foreach ($reservations as $reservation) {
                    $stock = StockItem::query()->where('product_id', $reservation->product_id)->lockForUpdate()->firstOrFail();
                    $qty = (float) $reservation->quantity;
                    $before = (float) $stock->quantity_on_hand;
                    $stock->quantity_on_hand = $before - $qty;
                    $stock->reserved_quantity = (float) $stock->reserved_quantity - $qty;
                    $stock->save();

                    $batch = null;
                    if ($reservation->stock_batch_id) {
                        $batch = StockBatch::query()->whereKey($reservation->stock_batch_id)->lockForUpdate()->firstOrFail();
                        $batch->quantity_on_hand = (float) $batch->quantity_on_hand - $qty;
                        $batch->reserved_quantity = (float) $batch->reserved_quantity - $qty;
                        $batch->save();
                    }

                    $this->movement($line->product, $batch, StockMovementType::OUT, $qty, $before, (float) $stock->quantity_on_hand, 'Livraison commande '.$sale->number, $user, $sale);
                    $reservation->update(['status' => ReservationStatus::CONSUMED->value]);
                }

                $this->alerts->refreshProduct($line->product->fresh(['stockItem', 'subsidiary']));
            }

            $this->audit->log('sale.stock_consumed', $sale, $sale->subsidiary, ['sale' => $sale->number]);
        });
    }

    public function sellSale(Sale $sale, User $user): void
    {
        DB::transaction(function () use ($sale, $user) {
            $sale->load('lines.product.subsidiary');

            foreach ($sale->lines as $line) {
                if (! $line->product_id) continue;

                $product = $line->product;
                $qty = (float) $line->quantity;
                $reason = 'Vente directe '.$sale->number;
                $stock = StockItem::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
                $available = (float) $stock->quantity_on_hand - (float) $stock->reserved_quantity;

                if ($qty > $available) {
                    throw ValidationException::withMessages(['stock' => "Stock insuffisant pour {$product->name} : disponible {$available}, demandé {$qty}."]);
                }

                if ($product->subsidiary->tracks_expiry) {
                    $this->consumeUnreservedBatches($product, $qty, $reason, $user, $stock, StockMovementType::OUT, $sale);
                } else {
                    $before = (float) $stock->quantity_on_hand;
                    $stock->quantity_on_hand = $before - $qty;
                    $stock->save();
                    $this->movement($product, null, StockMovementType::OUT, $qty, $before, (float) $stock->quantity_on_hand, $reason, $user, $sale);
                }

                $this->alerts->refreshProduct($product->fresh(['stockItem', 'subsidiary']));
            }

            $this->audit->log('sale.stock_sold_direct', $sale, $sale->subsidiary, ['sale' => $sale->number]);
        });
    }

    private function reserveLine(SaleLine $line, User $user): void
    {
        $product = $line->product;
        $qty = (float) $line->quantity;
        $stock = StockItem::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
        $available = (float) $stock->quantity_on_hand - (float) $stock->reserved_quantity;

        if ($qty > $available) {
            throw ValidationException::withMessages(['stock' => "Stock insuffisant pour {$product->name} : disponible {$available}, demandé {$qty}."]);
        }

        $stock->reserved_quantity = (float) $stock->reserved_quantity + $qty;
        $stock->save();

        if ($product->subsidiary->tracks_expiry) {
            $remaining = $qty;
            $batches = StockBatch::query()
                ->where('product_id', $product->id)
                ->where('quantity_on_hand', '>', 0)
                ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                $batchAvailable = (float) $batch->quantity_on_hand - (float) $batch->reserved_quantity;
                if ($batchAvailable <= 0) continue;
                $take = min($remaining, $batchAvailable);
                $batch->reserved_quantity = (float) $batch->reserved_quantity + $take;
                $batch->save();
                StockReservation::create(['sale_line_id' => $line->id, 'product_id' => $product->id, 'stock_batch_id' => $batch->id, 'quantity' => $take, 'status' => ReservationStatus::ACTIVE->value]);
                $remaining -= $take;
                if ($remaining <= 0.0001) break;
            }

            if ($remaining > 0.0001) {
                throw ValidationException::withMessages(['stock' => "Lots disponibles insuffisants pour {$product->name}."]);
            }
        } else {
            StockReservation::create(['sale_line_id' => $line->id, 'product_id' => $product->id, 'stock_batch_id' => null, 'quantity' => $qty, 'status' => ReservationStatus::ACTIVE->value]);
        }

        $this->movement($product, null, StockMovementType::RESERVE, $qty, (float) $stock->quantity_on_hand, (float) $stock->quantity_on_hand, 'Réservation commande '.$line->sale->number, $user, $line->sale);
    }

    protected function consumeUnreservedBatches(Product $product, float $quantity, string $reason, User $user, StockItem $stock, StockMovementType $type = StockMovementType::ADJUSTMENT_OUT, ?object $source = null): void
    {
        $remaining = $quantity;
        $batches = StockBatch::query()
            ->where('product_id', $product->id)
            ->where('quantity_on_hand', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->lockForUpdate()->get();

        foreach ($batches as $batch) {
            $available = (float) $batch->quantity_on_hand - (float) $batch->reserved_quantity;
            if ($available <= 0) continue;
            $take = min($remaining, $available);
            $before = (float) $stock->quantity_on_hand;
            $batch->quantity_on_hand = (float) $batch->quantity_on_hand - $take;
            $batch->save();
            $stock->quantity_on_hand = $before - $take;
            $stock->save();
            $this->movement($product, $batch, $type, $take, $before, (float) $stock->quantity_on_hand, $reason, $user, $source);
            $remaining -= $take;
            if ($remaining <= 0.0001) break;
        }

        if ($remaining > 0.0001) {
            throw ValidationException::withMessages(['quantity' => 'Quantité disponible par lots insuffisante.']);
        }
    }

    private function movement(Product $product, ?StockBatch $batch, StockMovementType $type, float $quantity, float $before, float $after, string $reason, User $user, ?object $source = null): void
    {
        StockMovement::create([
            'product_id' => $product->id,
            'stock_batch_id' => $batch?->id,
            'type' => $type->value,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->id,
            'reason' => $reason,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);
    }
}
