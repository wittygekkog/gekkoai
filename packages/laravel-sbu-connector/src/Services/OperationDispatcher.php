<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Services;

use Illuminate\Support\Facades\DB;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Contracts\SbuOperationHandler;
use WittyGekko\SbuConnector\Registry\OperationRegistry;
use WittyGekko\SbuConnector\Security\RequestFingerprint;

/**
 * Runs a registered handler. Read-only operations skip the idempotency ledger.
 * Writes commit the ledger and the business change in one database transaction.
 */
final class OperationDispatcher
{
    public function __construct(
        private readonly OperationRegistry $registry,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function validate(string $name, array $parameters): array
    {
        return $this->handler($name)->validate($parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(string $name, array $parameters, OperationContext $context): OperationResult
    {
        $definition = $this->registry->get($name);
        $handler = $this->handler($name);
        $parameters = $handler->validate($parameters);

        if ($definition->readOnly) {
            return $handler->execute($parameters, $context);
        }

        $fingerprint = RequestFingerprint::make($name, $parameters);

        return DB::transaction(function () use ($handler, $parameters, $context, $definition, $fingerprint): OperationResult {
            $claim = $this->idempotency->begin(
                $context->tenantId,
                $context->idempotencyKey,
                $definition->name,
                $fingerprint,
                $context->operationId,
            );

            if ($claim->replay) {
                return OperationResult::fromArray($claim->record->result ?? []);
            }

            $result = $handler->execute($parameters, $context);
            $this->idempotency->complete($claim->record, $result);

            return $result;
        }, 3);
    }

    private function handler(string $name): SbuOperationHandler
    {
        $handler = app($this->registry->get($name)->handler);
        if (! $handler instanceof SbuOperationHandler) {
            throw new \RuntimeException('The registered handler does not implement SbuOperationHandler.');
        }

        return $handler;
    }
}
