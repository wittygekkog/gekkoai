<?php

declare(strict_types=1);

namespace App\Services\Connectors\InnerCove;

use App\Services\Connectors\SimulatedRetreat;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Contracts\SbuOperationHandler;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;

/**
 * Mirrors Inner Cove's cancel(): the stay is released and no refund is created.
 */
final class CancelBookingHandler implements SbuOperationHandler
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
        $this->retreat->lockTenant($context->tenantId);
        $booking = $this->retreat->findByReference($context->tenantId, (string) $parameters['reference']);
        if ($booking === null) {
            throw new BusinessRuleException('reference', 'No booking uses that reference.', 'not_found');
        }
        if ($booking->status === 'cancelled') {
            return new OperationResult(true, 'completed', $this->retreat->present($booking), duplicate: true, executedAt: now()->toIso8601String());
        }

        $booking->status = 'cancelled';
        $booking->save();
        $booking->nights()->delete();

        $data = $this->retreat->present($booking->fresh());
        $data['refund_processed'] = false;

        return new OperationResult(true, 'completed', $data, executedAt: now()->toIso8601String());
    }
}
