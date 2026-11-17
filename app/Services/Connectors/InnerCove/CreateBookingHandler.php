<?php

declare(strict_types=1);

namespace App\Services\Connectors\InnerCove;

use App\Models\SimBooking;
use App\Services\Connectors\InnerCoveCalendar;
use App\Services\Connectors\SimulatedRetreat;
use Illuminate\Support\Str;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Contracts\SbuOperationHandler;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;

final class CreateBookingHandler implements SbuOperationHandler
{
    public function __construct(private readonly SimulatedRetreat $retreat) {}

    public function validate(array $parameters): array
    {
        return validator($parameters, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'mobile' => ['required', 'string', 'max:40'],
            'arrival' => ['required', 'date_format:Y-m-d'],
            'departure' => ['required', 'date_format:Y-m-d', 'after:arrival'],
            'guests' => ['required', 'integer', 'min:1', 'max:'.InnerCoveCalendar::MAX_GUESTS],
            'notes' => ['nullable', 'string', 'max:500'],
        ])->validate();
    }

    public function execute(array $parameters, OperationContext $context): OperationResult
    {
        $this->retreat->lockTenant($context->tenantId);
        $arrival = (string) $parameters['arrival'];
        $departure = (string) $parameters['departure'];
        if ($this->retreat->nightsTaken($context->tenantId, $arrival, $departure)) {
            throw new BusinessRuleException('arrival', 'These dates are no longer available.');
        }

        $nights = InnerCoveCalendar::nights($arrival, $departure);
        if (count($nights) < 1 || count($nights) > InnerCoveCalendar::MAX_NIGHTS) {
            throw new BusinessRuleException('departure', 'Departure must be after arrival, with a stay of 1 to 365 nights.');
        }

        $quote = InnerCoveCalendar::quote($arrival, $departure, (int) $parameters['guests']);
        $booking = new SimBooking();
        $booking->tenant_id = $context->tenantId;
        $booking->reference = (string) Str::uuid();
        $booking->idempotency_key = $context->idempotencyKey;
        $booking->guest_name = trim((string) $parameters['name']);
        $booking->email = strtolower((string) $parameters['email']);
        $booking->mobile = trim((string) $parameters['mobile']);
        $booking->arrival = $arrival;
        $booking->departure = $departure;
        $booking->guests = (int) $parameters['guests'];
        $booking->notes = $parameters['notes'] ?? null;
        $booking->status = 'pending';
        $booking->calculated_cents = $quote['calculated_cents'];
        $booking->total_cents = $quote['total_cents'];
        $booking->deposit_cents = $quote['deposit_cents'];
        $booking->deposit_percent = $quote['deposit_percent'];
        $booking->rate_index = $quote['rate_index'];
        $booking->created_by = $context->actorId;
        $booking->save();
        $this->retreat->holdNights($booking);

        return new OperationResult(
            true,
            'completed',
            $this->retreat->present($booking),
            executedAt: now()->toIso8601String(),
        );
    }
}
