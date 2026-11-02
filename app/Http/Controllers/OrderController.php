<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subsidiary;
use App\Services\AuditService;
use App\Services\NumberingService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::with(['subsidiary', 'client'])->latest('order_date');
        if ($request->filled('subsidiary_id')) $query->where('subsidiary_id', $request->subsidiary_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        return view('orders.index', ['orders' => $query->paginate(20)->withQueryString(), 'subsidiaries' => Subsidiary::where('active', true)->get()]);
    }

    public function create(): View
    {
        return view('orders.create', [
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'products' => Product::with('stockItem')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, NumberingService $numbering, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'subsidiary_id' => ['required', 'exists:subsidiaries,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'order_date' => ['required', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $subsidiary = Subsidiary::findOrFail($data['subsidiary_id']);
        if (! empty($data['client_id'])) {
            $client = Client::findOrFail($data['client_id']);
            if ($client->subsidiary_id && $client->subsidiary_id !== $subsidiary->id) {
                throw ValidationException::withMessages(['client_id' => 'Ce client est rattaché à une autre filiale.']);
            }
        }

        $products = Product::whereIn('id', collect($data['lines'])->pluck('product_id'))->get()->keyBy('id');
        foreach ($data['lines'] as $line) {
            $product = $products->get((int) $line['product_id']);
            if (! $product || $product->subsidiary_id !== $subsidiary->id) {
                throw ValidationException::withMessages(['lines' => 'Tous les produits doivent appartenir à la filiale de la commande.']);
            }
        }

        $order = DB::transaction(function () use ($data, $subsidiary, $products, $request, $numbering) {
            $number = $numbering->next(DocumentType::ORDER, $subsidiary, \Carbon\Carbon::parse($data['order_date']));
            $order = Order::create([
                'subsidiary_id' => $subsidiary->id,
                'client_id' => $data['client_id'] ?? null,
                'number' => $number,
                'status' => OrderStatus::PENDING->value,
                'order_date' => $data['order_date'],
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['lines'] as $line) {
                $product = $products->get((int) $line['product_id']);
                $order->lines()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'] ?? $product->sale_price,
                ]);
            }
            return $order;
        });
        $audit->log('order.created', $order, $subsidiary);
        return redirect()->route('orders.show', $order)->with('success', 'Commande créée.');
    }

    public function show(Order $order): View
    {
        $order->load('subsidiary.groupProfile', 'client', 'lines.product.stockItem', 'lines.reservations.batch', 'creator');
        return view('orders.show', compact('order'));
    }

    public function cancel(Request $request, Order $order, StockService $stock, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $order, $stock) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->isCancellable()) {
                throw ValidationException::withMessages(['status' => 'Cette commande ne peut plus être annulée.']);
            }
            $stock->releaseOrder($locked, $request->user());
            $locked->update(['status' => OrderStatus::CANCELLED->value, 'cancelled_at' => now()]);
        });

        $order->refresh();
        $audit->log('order.cancelled', $order, $order->subsidiary);
        return back()->with('success', 'Commande annulée. Les réservations de stock ont été libérées.');
    }

    public function advance(Request $request, Order $order, StockService $stock, AuditService $audit): RedirectResponse
    {
        $next = $order->status->next();
        if (! $next) {
            return back()->withErrors(['status' => 'Cette commande est déjà livrée.']);
        }

        DB::transaction(function () use ($request, $order, $next, $stock) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->next() !== $next) {
                throw ValidationException::withMessages(['status' => 'Le statut a été modifié par un autre utilisateur. Rechargez la page.']);
            }

            if ($next === OrderStatus::VALIDATED) {
                $stock->reserveOrder($locked, $request->user());
                $locked->update(['status' => $next->value, 'validated_at' => now()]);
            } elseif ($next === OrderStatus::PREPARATION) {
                $locked->update(['status' => $next->value, 'preparation_at' => now()]);
            } elseif ($next === OrderStatus::DELIVERED) {
                $stock->deliverOrder($locked, $request->user());
                $locked->update(['status' => $next->value, 'delivered_at' => now()]);
            }
        });

        $order->refresh();
        $audit->log('order.status_changed', $order, $order->subsidiary, ['status' => $order->status->value]);
        return back()->with('success', 'Commande passée au statut : '.$order->status->label().'.');
    }
}
