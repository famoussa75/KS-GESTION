<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Subsidiary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function log(string $action, ?Model $subject = null, ?Subsidiary $subsidiary = null, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'subsidiary_id' => $subsidiary?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
