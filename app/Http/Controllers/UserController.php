<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('subsidiaries')->orderBy('name')->get(),
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'subsidiaries' => ['array'],
            'subsidiaries.*' => ['integer', 'exists:subsidiaries,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'active' => true,
        ]);
        $user->subsidiaries()->sync($data['subsidiaries'] ?? []);
        $audit->log('user.created', $user);
        return back()->with('success', 'Utilisateur créé.');
    }

    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', 'min:10'],
            'active' => ['nullable', 'boolean'],
            'subsidiaries' => ['array'],
            'subsidiaries.*' => ['integer', 'exists:subsidiaries,id'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->active = $request->boolean('active');
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->subsidiaries()->sync($data['subsidiaries'] ?? []);
        $audit->log('user.updated', $user);
        return back()->with('success', 'Utilisateur mis à jour.');
    }
}
