<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Carbon\CarbonImmutable;

/**
 * Resolves calendar dates from an admin instruction.
 * Relative phrases stay unresolved so the platform asks instead of guessing.
 */
final class DateRangeParser
{
    /** @var array<string, int> */
    private const MONTHS = [
        'january' => 1, 'jan' => 1,
        'february' => 2, 'feb' => 2,
        'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'may' => 5,
        'june' => 6, 'jun' => 6,
        'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'october' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'december' => 12, 'dec' => 12,
    ];

    /**
     * @return array{arrival: ?string, departure: ?string, ambiguous: bool, reason: ?string}
     */
    public function parse(string $text, string $referenceAt, string $timezone = 'Asia/Manila'): array
    {
        if (preg_match('/\b(next weekend|tomorrow|next week|sometime|as soon as possible)\b/i', $text) === 1) {
            return $this->ambiguous('The date is relative. Please give the arrival and departure dates.');
        }

        $month = implode('|', array_keys(self::MONTHS));
        if (preg_match('/\b(?:'.$month.')\s+\d{1,2}(?:st|nd|rd|th)?\s+(?:or|\/)\s+\d{1,2}\b/i', $text) === 1) {
            return $this->ambiguous('More than one date was mentioned. Which dates should be used?');
        }

        $reference = CarbonImmutable::parse($referenceAt, $timezone)->startOfDay();

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\s*(?:to|through|until|-|–|—)\s*(\d{4}-\d{2}-\d{2})\b/', $text, $iso) === 1) {
            return $this->range($iso[1], $iso[2]);
        }

        $pattern = '/\b('.$month.')\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s*,?\s*(\d{4}))?\s*(?:to|through|until|-|–|—)\s*(?:('.$month.')\s+)?(\d{1,2})(?:st|nd|rd|th)?(?:\s*,?\s*(\d{4}))?/i';
        if (preg_match($pattern, $text, $match) === 1) {
            $monthNumber = self::MONTHS[strtolower($match[1])];
            $yearOne = ($match[3] ?? '') !== '' ? (int) $match[3] : null;
            $secondMonth = ($match[4] ?? '') !== '' ? self::MONTHS[strtolower($match[4])] : $monthNumber;
            $yearTwo = ($match[6] ?? '') !== '' ? (int) $match[6] : $yearOne;
            $arrival = $this->resolve($yearOne, $monthNumber, (int) $match[2], $reference);
            $departure = $this->resolve($yearTwo, $secondMonth, (int) $match[5], $reference);
            if ($arrival === null || $departure === null) {
                return $this->ambiguous('One of those dates is not a real calendar date.');
            }
            if ($departure <= $arrival && $yearOne === null && $yearTwo === null) {
                $departure = CarbonImmutable::parse($departure, $timezone)->addYear()->toDateString();
            }

            return $this->range($arrival, $departure);
        }

        if (preg_match('/\b('.$month.')\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s*,?\s*(\d{4}))?\b/i', $text, $single) === 1) {
            $year = ($single[3] ?? '') !== '' ? (int) $single[3] : null;
            $date = $this->resolve($year, self::MONTHS[strtolower($single[1])], (int) $single[2], $reference);
            if ($date === null) {
                return $this->ambiguous('That date is not a real calendar date.');
            }

            return ['arrival' => $date, 'departure' => null, 'ambiguous' => false, 'reason' => null];
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $oneIso) === 1) {
            return ['arrival' => $oneIso[1], 'departure' => null, 'ambiguous' => false, 'reason' => null];
        }

        return ['arrival' => null, 'departure' => null, 'ambiguous' => false, 'reason' => null];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array{arrival: ?string, departure: ?string, ambiguous: bool, reason: ?string}  $parsed
     * @return array{arrival: ?string, departure: ?string, ambiguous: bool, reason: ?string}
     */
    public function merge(array $draft, array $parsed, string $text): array
    {
        if ($parsed['ambiguous']) {
            return $parsed;
        }

        $arrival = $draft['arrival'] ?? null;
        $departure = $draft['departure'] ?? null;
        $correction = preg_match('/\b(actually|make that|change (that|it|the)|instead)\b/i', $text) === 1;

        if ($parsed['arrival'] !== null && $parsed['departure'] !== null) {
            return $parsed;
        }

        if ($parsed['arrival'] !== null && $parsed['departure'] === null) {
            if ($arrival === null) {
                return ['arrival' => $parsed['arrival'], 'departure' => is_string($departure) ? $departure : null, 'ambiguous' => false, 'reason' => null];
            }
            if (($departure === null || $departure === '') && $parsed['arrival'] > $arrival) {
                return ['arrival' => $arrival, 'departure' => $parsed['arrival'], 'ambiguous' => false, 'reason' => null];
            }
            if ($correction && is_string($departure) && $parsed['arrival'] < $departure) {
                return ['arrival' => $parsed['arrival'], 'departure' => $departure, 'ambiguous' => false, 'reason' => null];
            }
            if ($correction && is_string($departure) && $parsed['arrival'] > $arrival) {
                return ['arrival' => $arrival, 'departure' => $parsed['arrival'], 'ambiguous' => false, 'reason' => null];
            }
        }

        return [
            'arrival' => is_string($arrival) ? $arrival : null,
            'departure' => is_string($departure) ? $departure : null,
            'ambiguous' => false,
            'reason' => null,
        ];
    }

    private function resolve(?int $year, int $month, int $day, CarbonImmutable $reference): ?string
    {
        $explicit = $year !== null;
        $year ??= (int) $reference->year;
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = CarbonImmutable::create($year, $month, $day, 0, 0, 0, $reference->timezone)->startOfDay();
        if (! $explicit && $date->lt($reference)) {
            if (! checkdate($month, $day, $year + 1)) {
                return null;
            }
            $date = $date->addYear();
        }

        return $date->toDateString();
    }

    /**
     * @return array{arrival: ?string, departure: ?string, ambiguous: bool, reason: ?string}
     */
    private function range(string $arrival, string $departure): array
    {
        if ($departure <= $arrival) {
            return $this->ambiguous('Departure must be after arrival. Check-out is 12:00 on the departure date.');
        }

        return ['arrival' => $arrival, 'departure' => $departure, 'ambiguous' => false, 'reason' => null];
    }

    /**
     * @return array{arrival: ?string, departure: ?string, ambiguous: bool, reason: ?string}
     */
    private function ambiguous(string $reason): array
    {
        return ['arrival' => null, 'departure' => null, 'ambiguous' => true, 'reason' => $reason];
    }
}
