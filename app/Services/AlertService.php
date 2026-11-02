<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Product;
use App\Models\StockBatch;
use Carbon\Carbon;

class AlertService
{
    public function refreshProduct(Product $product): void
    {
        $product->loadMissing('stockItem', 'subsidiary');
        $available = $product->availableQuantity();
        $threshold = (float) $product->low_stock_threshold;
        $fingerprint = 'low_stock:'.$product->id;

        if ($available <= $threshold) {
            Alert::updateOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'subsidiary_id' => $product->subsidiary_id,
                    'type' => AlertType::LOW_STOCK->value,
                    'title' => 'Stock faible — '.$product->name,
                    'message' => sprintf('Disponible : %.3f %s. Seuil : %.3f.', $available, $product->unit, $threshold),
                    'active' => true,
                    'resolved_at' => null,
                ]
            );
        } else {
            Alert::where('fingerprint', $fingerprint)->where('active', true)->update([
                'active' => false,
                'resolved_at' => now(),
            ]);
        }

        if ($product->subsidiary?->tracks_expiry) {
            $this->refreshExpiryAlerts($product);
        }
    }

    public function refreshExpiryAlerts(Product $product): void
    {
        $batchIds = [];
        $cutoff = Carbon::today()->addDays(30);

        foreach ($product->batches()->where('quantity_on_hand', '>', 0)->get() as $batch) {
            $fingerprint = 'expiry:'.$batch->id;
            $batchIds[] = $fingerprint;

            if ($batch->expiry_date && $batch->expiry_date->lte($cutoff)) {
                $days = Carbon::today()->diffInDays($batch->expiry_date, false);
                $label = $days < 0 ? 'périmé' : ($days === 0 ? 'expire aujourd’hui' : "expire dans {$days} jour(s)");
                Alert::updateOrCreate(
                    ['fingerprint' => $fingerprint],
                    [
                        'subsidiary_id' => $product->subsidiary_id,
                        'type' => AlertType::EXPIRY->value,
                        'title' => 'Péremption — '.$product->name,
                        'message' => "Lot {$batch->batch_number} : {$label}. Quantité : {$batch->quantity_on_hand} {$product->unit}.",
                        'active' => true,
                        'resolved_at' => null,
                    ]
                );
            } else {
                Alert::where('fingerprint', $fingerprint)->where('active', true)->update(['active' => false, 'resolved_at' => now()]);
            }
        }

        $allFingerprints = $product->batches()->pluck('id')->map(fn ($id) => 'expiry:'.$id)->all();
        if ($allFingerprints) {
            Alert::where('type', AlertType::EXPIRY->value)
                ->whereIn('fingerprint', $allFingerprints)
                ->whereNotIn('fingerprint', $batchIds ?: ['__none__'])
                ->where('active', true)
                ->update(['active' => false, 'resolved_at' => now()]);
        }
    }

    public function refreshAll(): int
    {
        $count = 0;
        Product::with('stockItem', 'subsidiary')->where('active', true)->chunkById(100, function ($products) use (&$count) {
            foreach ($products as $product) {
                $this->refreshProduct($product);
                $count++;
            }
        });

        return $count;
    }
}
