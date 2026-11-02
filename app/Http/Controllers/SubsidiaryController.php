<?php

namespace App\Http\Controllers;

use App\Models\Subsidiary;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SubsidiaryController extends Controller
{
    public function index(): View
    {
        return view('subsidiaries.index', ['subsidiaries' => Subsidiary::with('groupProfile')->orderBy('name')->get()]);
    }

    public function edit(Subsidiary $subsidiary): View
    {
        return view('subsidiaries.edit', compact('subsidiary'));
    }

    public function update(Request $request, Subsidiary $subsidiary, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'activity' => ['required', 'string', 'max:180'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'tracks_expiry' => ['nullable', 'boolean'],
            'default_shelf_life_months' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('subsidiary-logos', 'public');
        }
        unset($data['logo']);
        $data['tracks_expiry'] = $request->boolean('tracks_expiry');

        $subsidiary->update($data);
        $audit->log('subsidiary.updated', $subsidiary, $subsidiary);
        return redirect()->route('subsidiaries.index')->with('success', 'Filiale mise à jour.');
    }
}
