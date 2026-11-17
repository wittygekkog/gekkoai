<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\SimBooking;
use App\Models\SimReservedNight;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Simulation of The Inner Cove retreat calendar.
 * Rows live in this platform's database, never in the Inner Cove database.
 */
final class SimulatedRetreat
{
    public function nightsTaken(string $tenantId, string $arrival, string $departure, ?string $exceptBookingId = null): bool
    {
        $query = SimReservedNight::query()
            ->where('tenant_id', $tenantId)
            ->where('night', '>=', $arrival)
            ->where('night', '<', $departure);
        if ($exceptBookingId !== null) {
            $query->where('booking_id', '!=', $exceptBookingId);
        }

        return $query->exists();
    }

    public function lockTenant(string $tenantId): void
    {
        Tenant::query()->where('id', $tenantId)->lockForUpdate()->first();
    }

    public function findByReference(string $tenantId, string $reference): ?SimBooking
    {
        $reference = strtolower(trim($reference));
        if (Str::isUuid($reference)) {
            return SimBooking::query()->where('tenant_id', $tenantId)->where('reference', $reference)->first();
        }
        if (preg_match('/^[0-9a-f]{8}$/', $reference) !== 1) {
            return null;
        }

        $matches = SimBooking::query()
            ->where('tenant_id', $tenantId)
            ->where('reference', 'like', $reference.'%')
            ->limit(2)
            ->get();
        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(SimBooking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'reference' => $booking->reference,
            'short_reference' => $booking->shortReference(),
            'status' => $booking->status,
            'guest_name' => $booking->guest_name,
            'email' => $booking->email,
            'mobile' => $booking->mobile,
            'arrival' => $booking->arrival->toDateString(),
            'departure' => $booking->departure->toDateString(),
            'guests' => (int) $booking->guests,
            'nights' => count(InnerCoveCalendar::nights($booking->arrival->toDateString(), $booking->departure->toDateString())),
            'accommodation' => 'The Inner Cove',
            'total_cents' => (int) $booking->total_cents,
            'total' => InnerCoveCalendar::peso((int) $booking->total_cents),
            'deposit' => InnerCoveCalendar::peso((int) $booking->deposit_cents),
            'deposit_percent' => (int) $booking->deposit_percent,
            'check_in' => InnerCoveCalendar::CHECK_IN,
            'check_out' => InnerCoveCalendar::CHECK_OUT,
            'refund_processed' => false,
            'rate_note' => 'Simulation quote uses the published base rate at index 100. Seasonal rates are applied only inside The Inner Cove application.',
        ];
    }

    public function holdNights(SimBooking $booking): void
    {
        foreach (InnerCoveCalendar::nights($booking->arrival->toDateString(), $booking->departure->toDateString()) as $night) {
            $row = new SimReservedNight();
            $row->tenant_id = $booking->tenant_id;
            $row->booking_id = $booking->id;
            $row->night = $night;
            $row->save();
        }
    }
}
