<?php

namespace App\Services;

use App\Models\Subsidiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SubsidiaryAccess
{
    public function list(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Subsidiary::where('active', true)->orderBy('name')->get();
        }
        return $user->subsidiaries()->where('active', true)->orderBy('name')->get();
    }

    public function assert(User $user, int $subsidiaryId): void
    {
        if (! $user->canAccessSubsidiary($subsidiaryId)) {
            abort(403, 'Accès interdit à cette filiale.');
        }
    }
}
