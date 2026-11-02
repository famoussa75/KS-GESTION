<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->active) {
            abort(403);
        }

        $role = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;
        if (! in_array($role, $roles, true)) {
            abort(403, 'Vous ne disposez pas des droits nécessaires pour ce module.');
        }

        return $next($request);
    }
}
