<?php

namespace App\Http\Controllers;

use App\Models\GroupProfile;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['group' => GroupProfile::firstOrFail()]);
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $group = GroupProfile::firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'rccm' => ['nullable', 'string', 'max:80'],
            'nif' => ['nullable', 'string', 'max:80'],
            'rib' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
        ]);
        $group->update($data);
        $audit->log('group.settings_updated', $group);
        return back()->with('success', 'Informations légales mises à jour.');
    }
}
