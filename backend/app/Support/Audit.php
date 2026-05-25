<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class Audit
{
    public static function log(
        string $eventType,
        string $entityType,
        int|string|null $entityId = null,
        ?User $user = null,
        ?Request $request = null,
        array $payload = [],
    ): void {
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'actor_role' => $user?->role,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
