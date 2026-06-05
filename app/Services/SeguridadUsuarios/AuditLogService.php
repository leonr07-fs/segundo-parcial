<?php

namespace App\Services\SeguridadUsuarios;

use App\Models\ReportesAuditoria\AuditLog;
use App\Models\Seguridad\User;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(string $event, ?User $user, Request $request, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
