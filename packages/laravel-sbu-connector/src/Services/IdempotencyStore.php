<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Services;

use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Exceptions\IdempotencyConflictException;
use WittyGekko\SbuConnector\Models\IdempotencyRecord;

/**
 * One idempotency key maps to one payload. Reuse with the same payload returns
 * the stored outcome. Reuse with a different payload is rejected.
 */
final class IdempotencyStore
{
    public function begin(
        string $tenantId,
        string $key,
        string $operation,
        string $fingerprint,
        string $operationId,
    ): IdempotencyClaim {
        $existing = IdempotencyRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof IdempotencyRecord) {
            if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                throw new IdempotencyConflictException('This idempotency key was already used with a different request.');
            }
            if (in_array($existing->status, ['completed', 'failed'], true)) {
                return new IdempotencyClaim($existing, true);
            }

            throw new IdempotencyConflictException('This request is already being processed.');
        }

        $record = new IdempotencyRecord();
        $record->tenant_id = $tenantId;
        $record->idempotency_key = $key;
        $record->operation_name = $operation;
        $record->request_fingerprint = $fingerprint;
        $record->status = 'processing';
        $record->operation_id = $operationId;
        $record->save();

        return new IdempotencyClaim($record, false);
    }

    public function complete(IdempotencyRecord $record, OperationResult $result): void
    {
        $record->status = $result->success ? 'completed' : 'failed';
        $record->result = $result->toArray();
        $record->save();
    }

    public function findByOperationId(string $tenantId, string $operationId): ?IdempotencyRecord
    {
        $record = IdempotencyRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('operation_id', $operationId)
            ->first();

        return $record instanceof IdempotencyRecord ? $record : null;
    }

    public function findByKey(string $tenantId, string $key): ?IdempotencyRecord
    {
        $record = IdempotencyRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $key)
            ->first();

        return $record instanceof IdempotencyRecord ? $record : null;
    }
}
