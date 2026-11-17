<?php

declare(strict_types=1);

namespace App\Services\Connectors\InnerCove;

use App\Services\Connectors\InnerCoveCalendar;
use App\Services\Connectors\SimulatedRetreat;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Contracts\SbuOperationHandler;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;

final class CheckAvailabilityHandler implements SbuOperationHandler
{
    public function __construct(private readonly SimulatedRetreat $retreat) {}

    public function validate(array $parameters): array
    {
        $data = validator($parameters, [
            'arrival' => ['required', 'date_format:Y-m-d'],
            'departure' => ['required', 'date_format:Y-m-d', 'after:arrival'],
            'guests' => ['required', 'integer', 'min:1', 'max:'.InnerCoveCalendar::MAX_GUESTS],
            'unit_id' => ['nullable', 'string', 'max:64'],
            'exclude_reference' => ['nullable', 'uuid'],
        ])->validate();

        if (! empty($data['unit_id']) && $data['unit_id'] !== 'the-inner-cove') {
            throw new BusinessRuleException('unit_id', 'The Inner Cove is reserved as one retreat. Omit unit_id or use the whole property.');
        }

        return $data;
    }

    public function execute(array $parameters, OperationContext $context): OperationResult
    {
        $except = null;
        if (! empty($parameters['exclude_reference'])) {
            $except = $this->retreat->findByReference($context->tenantId, (string) $parameters['exclude_reference'])?->id;
        }
        $taken = $this->retreat->nightsTaken(
            $context->tenantId,
            (string) $parameters['arrival'],
            (string) $parameters['departure'],
            $except,
        );
        $quote = InnerCoveCalendar::quote(
            (string) $parameters['arrival'],
            (string) $parameters['departure'],
            (int) $parameters['guests'],
        );
        $available = ! $taken && $quote['nights'] >= 1 && $quote['nights'] <= InnerCoveCalendar::MAX_NIGHTS;

        return new OperationResult(true, 'completed', array_merge($quote, [
            'available' => $available,
            'accommodation' => 'The Inner Cove',
            'occupancy_suitable' => (int) $parameters['guests'] <= InnerCoveCalendar::MAX_GUESTS,
            'availability' => $available ? 'These dates are free.' : 'These dates are already reserved.',
        ]), executedAt: now()->toIso8601String());
    }
}
