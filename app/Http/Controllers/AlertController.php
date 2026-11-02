<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\SubsidiaryAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request, SubsidiaryAccess $access): View
    {
        $ids = $access->list($request->user())->pluck('id');
        $query = Alert::with('subsidiary')->whereIn('subsidiary_id', $ids)->where('active', true)->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return view('alerts.index', [
            'alerts' => $query->paginate(30)->withQueryString(),
        ]);
    }
}
