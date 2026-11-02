<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Subsidiary;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $query = Client::with('subsidiary')->latest();
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return view('clients.index', [
            'clients' => $query->paginate(20)->withQueryString(),
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'subsidiary_id' => ['nullable', 'exists:subsidiaries,id'],
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'nif' => ['nullable', 'string', 'max:80'],
        ]);
        $client = Client::create($data);
        $audit->log('client.created', $client, $client->subsidiary);
        return back()->with('success', 'Client créé.');
    }

    public function update(Request $request, Client $client, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'subsidiary_id' => ['nullable', 'exists:subsidiaries,id'],
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'nif' => ['nullable', 'string', 'max:80'],
        ]);
        $client->update($data);
        $audit->log('client.updated', $client, $client->subsidiary);
        return back()->with('success', 'Client mis à jour.');
    }
}
