<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAuditLog;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        ?string $tenantId,
        ?string $actorId,
        string $event,
        ?string $resourceType,
        ?string $resourceId,
        ?string $correlationId,
        array $metadata = [],
    ): void {
        $log = new AiAuditLog();
        $log->tenant_id = $tenantId;
        $log->actor_id = $actorId;
        $log->event_type = $event;
        $log->resource_type = $resourceType;
        $log->resource_id = $resourceId;
        $log->correlation_id = $correlationId;
        $log->metadata = $metadata;
        $log->created_at = now();
        $log->save();
    }
}
