<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\Product;
use App\Models\StockItem;
use App\Services\AuditService;
use App\Services\SubsidiaryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, SubsidiaryAccess $access): View
    {
        $subsidiaries = $access->list($request->user());
        $ids = $subsidiaries->pluck('id');
        $query = Product::with(['subsidiary', 'stockItem', 'revenueAccount'])->whereIn('subsidiary_id', $ids);

        if ($request->filled('subsidiary_id')) {
            $access->assert($request->user(), (int) $request->subsidiary_id);
            $query->where('subsidiary_id', $request->subsidiary_id);
        }
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(fn ($b) => $b->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
        }

        return view('products.index', [
            'products' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'subsidiaries' => $subsidiaries,
            'revenueAccounts' => AccountingAccount::where('type', 'revenue')->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, SubsidiaryAccess $access, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'subsidiary_id' => ['required', 'exists:subsidiaries,id'],
            'sku' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:20'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'revenue_account_id' => ['nullable', 'exists:accounting_accounts,id'],
        ]);
        $access->assert($request->user(), (int) $data['subsidiary_id']);

        $exists = Product::where('subsidiary_id', $data['subsidiary_id'])->where('sku', $data['sku'])->exists();
        if ($exists) {
            return back()->withErrors(['sku' => 'Ce SKU existe déjà dans cette filiale.'])->withInput();
        }

        $product = DB::transaction(function () use ($data) {
            $product = Product::create($data + ['active' => true]);
            StockItem::create(['product_id' => $product->id, 'quantity_on_hand' => 0, 'reserved_quantity' => 0]);
            return $product;
        });
        $audit->log('product.created', $product, $product->subsidiary);
        return back()->with('success', 'Produit créé.');
    }

    public function update(Request $request, Product $product, SubsidiaryAccess $access, AuditService $audit): RedirectResponse
    {
        $access->assert($request->user(), $product->subsidiary_id);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:20'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'revenue_account_id' => ['nullable', 'exists:accounting_accounts,id'],
            'active' => ['nullable', 'boolean'],
        ]);
        if (Product::where('subsidiary_id', $product->subsidiary_id)->where('sku', $data['sku'])->whereKeyNot($product->id)->exists()) {
            return back()->withErrors(['sku' => 'Ce SKU existe déjà dans cette filiale.']);
        }
        $data['active'] = $request->boolean('active');
        $product->update($data);
        $audit->log('product.updated', $product, $product->subsidiary);
        return back()->with('success', 'Produit mis à jour.');
    }
}
