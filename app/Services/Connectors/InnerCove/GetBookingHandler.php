<?php

declare(strict_types=1);

namespace App\Services\Connectors\InnerCove;

use App\Services\Connectors\SimulatedRetreat;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Contracts\SbuOperationHandler;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;

final class GetBookingHandler implements SbuOperationHandler
{
    public function __construct(private readonly SimulatedRetreat $retreat) {}

    public function validate(array $parameters): array
    {
        return validator($parameters, [
            'reference' => ['required', 'string', 'max:64'],
        ])->validate();
    }

    public function execute(array $parameters, OperationContext $context): OperationResult
    {
        $booking = $this->retreat->findByReference($context->tenantId, (string) $parameters['reference']);
        if ($booking === null) {
            throw new BusinessRuleException('reference', 'No booking uses that reference.', 'not_found');
        }

        return new OperationResult(true, 'completed', $this->retreat->present($booking), executedAt: now()->toIso8601String());
    }
}
