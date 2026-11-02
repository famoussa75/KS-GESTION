<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Services\StockService;
use App\Services\SubsidiaryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request, SubsidiaryAccess $access): View
    {
        $subsidiaries = $access->list($request->user());
        $ids = $subsidiaries->pluck('id');
        $query = Product::with(['subsidiary', 'stockItem', 'batches'])
            ->whereIn('subsidiary_id', $ids)
            ->where('active', true);

        if ($request->filled('subsidiary_id')) {
            $access->assert($request->user(), (int) $request->subsidiary_id);
            $query->where('subsidiary_id', $request->subsidiary_id);
        }

        return view('stock.index', [
            'products' => $query->orderBy('name')->paginate(30)->withQueryString(),
            'subsidiaries' => $subsidiaries,
            'isConsolidated' => $request->user()->isAdmin() && ! $request->filled('subsidiary_id'),
        ]);
    }

    public function show(Request $request, Product $product, SubsidiaryAccess $access): View
    {
        $access->assert($request->user(), $product->subsidiary_id);
        $product->load(['subsidiary', 'stockItem', 'batches' => fn ($q) => $q->orderBy('expiry_date'), 'revenueAccount']);
        $movements = $product->hasMany(\App\Models\StockMovement::class)->with('batch', 'creator')->latest('occurred_at')->paginate(25);
        return view('stock.show', compact('product', 'movements'));
    }

    public function adjust(Request $request, Product $product, SubsidiaryAccess $access, StockService $stock): RedirectResponse
    {
        $access->assert($request->user(), $product->subsidiary_id);
        $data = $request->validate([
            'type' => ['required', Rule::in([StockMovementType::ADJUSTMENT_IN->value, StockMovementType::ADJUSTMENT_OUT->value])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $stock->adjust($product, StockMovementType::from($data['type']), (float) $data['quantity'], $data['reason'], $request->user());
        return back()->with('success', 'Mouvement de stock enregistré.');
    }

    public function addBatch(Request $request, Product $product, SubsidiaryAccess $access, StockService $stock): RedirectResponse
    {
        $access->assert($request->user(), $product->subsidiary_id);
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:80'],
            'production_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        if ($product->batches()->where('batch_number', $data['batch_number'])->exists()) {
            return back()->withErrors(['batch_number' => 'Ce numéro de lot existe déjà pour ce produit.']);
        }
        $stock->addBatch($product, $data['batch_number'], $data['production_date'], (float) $data['quantity'], $request->user());
        return back()->with('success', 'Lot ajouté. La date de péremption a été calculée automatiquement à +6 mois.');
    }
}
