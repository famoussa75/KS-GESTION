<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockItem;
use App\Services\SubsidiaryAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SubsidiaryAccess $access): View
    {
        $ids = $access->list($request->user())->pluck('id');

        $invoiceTotal = (float) Invoice::whereIn('subsidiary_id', $ids)->sum('total');
        $paid = (float) \App\Models\InvoicePayment::whereHas('invoice', fn ($q) => $q->whereIn('subsidiary_id', $ids))->sum('amount');
        $openCash = CashSession::whereNull('closed_at')->whereHas('register', fn ($q) => $q->whereIn('subsidiary_id', $ids))->count();
        $stockUnits = (float) StockItem::whereHas('product', fn ($q) => $q->whereIn('subsidiary_id', $ids))->sum('quantity_on_hand');

        return view('dashboard.index', [
            'invoiceTotal' => $invoiceTotal,
            'outstandingTotal' => max(0, $invoiceTotal - $paid),
            'openCash' => $openCash,
            'stockUnits' => $stockUnits,
            'pendingOrders' => Sale::whereIn('subsidiary_id', $ids)->where('intent', 'order')->whereNotIn('status', ['delivered', 'invoiced', 'cancelled'])->count(),
            'productCount' => Product::whereIn('subsidiary_id', $ids)->count(),
            'alerts' => Alert::whereIn('subsidiary_id', $ids)->where('active', true)->latest()->limit(8)->get(),
            'recentInvoices' => Invoice::whereIn('subsidiary_id', $ids)->latest('issue_date')->limit(6)->get(),
        ]);
    }
}
