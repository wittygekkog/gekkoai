<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Contracts;

/**
 * Identity for one execution. The central platform builds this from the
 * authenticated admin session. Values proposed by a model are never copied here.
 */
final readonly class OperationContext
{
    public function __construct(
        public string $requestId,
        public string $operationId,
        public string $tenantId,
        public string $idempotencyKey,
        public string $actorId,
        public ?string $approvalReference,
        public string $correlationId,
    ) {}
}
