<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Every action worth answering for lands in audit_log, attributed to a named
 * signed-in user. The table is append-only at the database level, so what is
 * written here cannot later be tidied up.
 */
class AuditLogger
{
    public function record(
        string $action,
        string $entity,
        ?string $entityId,
        string $detail,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'detail' => $detail,
            'before' => $before,
            'after' => $after,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500) ?: null,
        ]);
    }
}
