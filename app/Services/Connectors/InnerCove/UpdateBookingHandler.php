<?php

declare(strict_types=1);

namespace App\Services\Connectors\InnerCove;

use App\Services\Connectors\InnerCoveCalendar;
use App\Services\Connectors\SimulatedRetreat;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Contracts\SbuOperationHandler;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;

final class UpdateBookingHandler implements SbuOperationHandler
{
    public function __construct(private readonly SimulatedRetreat $retreat) {}

    public function validate(array $parameters): array
    {
        return validator($parameters, [
            'reference' => ['required', 'string', 'max:64'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:40'],
            'arrival' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'departure' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'guests' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.InnerCoveCalendar::MAX_GUESTS],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
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
            throw new BusinessRuleException('reference', 'A cancelled booking cannot be changed.');
        }

        $arrival = (string) ($parameters['arrival'] ?? $booking->arrival->toDateString());
        $departure = (string) ($parameters['departure'] ?? $booking->departure->toDateString());
        if ($departure <= $arrival) {
            throw new BusinessRuleException('departure', 'Departure must be after arrival.');
        }
        if ($this->retreat->nightsTaken($context->tenantId, $arrival, $departure, $booking->id)) {
            throw new BusinessRuleException('arrival', 'These dates are no longer available.');
        }

        $guests = (int) ($parameters['guests'] ?? $booking->guests);
        $quote = InnerCoveCalendar::quote($arrival, $departure, $guests);
        $booking->guest_name = trim((string) ($parameters['name'] ?? $booking->guest_name));
        $booking->email = strtolower((string) ($parameters['email'] ?? $booking->email));
        $booking->mobile = trim((string) ($parameters['mobile'] ?? $booking->mobile));
        $booking->arrival = $arrival;
        $booking->departure = $departure;
        $booking->guests = $guests;
        $booking->notes = $parameters['notes'] ?? $booking->notes;
        $booking->calculated_cents = $quote['calculated_cents'];
        $booking->total_cents = $quote['total_cents'];
        $booking->deposit_cents = $quote['deposit_cents'];
        $booking->save();
        $booking->nights()->delete();
        $this->retreat->holdNights($booking->fresh());

        return new OperationResult(true, 'completed', $this->retreat->present($booking->fresh()), executedAt: now()->toIso8601String());
    }
}
