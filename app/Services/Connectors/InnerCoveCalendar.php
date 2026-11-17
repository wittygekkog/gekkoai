<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use Carbon\CarbonImmutable;

/**
 * Published Inner Cove stay rules copied from StayCalendar.
 * The live seasonal rate index is NOT read, because this platform does not
 * open the Inner Cove database. Simulation quotes use index 100 and say so.
 */
final class InnerCoveCalendar
{
    public const TIMEZONE = 'Asia/Manila';

    public const CHECK_IN = '14:00';

    public const CHECK_OUT = '12:00';

    public const INCLUDED_GUESTS = 8;

    public const MAX_GUESTS = 18;

    public const MAX_NIGHTS = 365;

    public const NIGHTLY_CENTS = 1_250_000;

    public const EXTRA_GUEST_CENTS = 150_000;

    public const DEFAULT_DEPOSIT_PERCENT = 30;

    public const RATE_INDEX = 100;

    /**
     * @return list<string>
     */
    public static function nights(string $arrival, string $departure): array
    {
        $cursor = CarbonImmutable::parse($arrival, self::TIMEZONE)->startOfDay();
        $end = CarbonImmutable::parse($departure, self::TIMEZONE)->startOfDay();
        $nights = [];
        while ($cursor->lt($end) && count($nights) <= self::MAX_NIGHTS) {
            $nights[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $nights;
    }

    /**
     * @return array<string, mixed>
     */
    public static function quote(string $arrival, string $departure, int $guests): array
    {
        $nights = self::nights($arrival, $departure);
        $base = self::NIGHTLY_CENTS + (max(0, $guests - self::INCLUDED_GUESTS) * self::EXTRA_GUEST_CENTS);
        $total = 0;
        foreach ($nights as $ignored) {
            $total += (int) round($base * self::RATE_INDEX / 100);
        }
        $deposit = (int) round($total * self::DEFAULT_DEPOSIT_PERCENT / 100);

        return [
            'nights' => count($nights),
            'night_dates' => $nights,
            'guests' => $guests,
            'calculated_cents' => $total,
            'total_cents' => $total,
            'deposit_cents' => $deposit,
            'deposit_percent' => self::DEFAULT_DEPOSIT_PERCENT,
            'rate_index' => self::RATE_INDEX,
            'calculated' => self::peso($total),
            'deposit' => self::peso($deposit),
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'rate_note' => 'Simulation quote uses the published base rate at index 100. Seasonal rates are applied only inside The Inner Cove application.',
        ];
    }

    public static function peso(int $cents): string
    {
        return '₱'.number_format($cents / 100, 2);
    }
}
