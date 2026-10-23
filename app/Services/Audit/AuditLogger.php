<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function log(string $action, ?Model $target = null, array $details = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'user',
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'details' => $details ?: null,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function logSystem(string $action, array $details = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => null,
            'actor_type' => 'system',
            'action' => $action,
            'target_type' => null,
            'target_id' => null,
            'details' => $details ?: null,
            'ip_address' => null,
        ]);
    }
}
