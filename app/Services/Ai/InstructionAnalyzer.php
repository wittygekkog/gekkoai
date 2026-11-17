<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Bounded instruction reader. It selects an intent and parameters.
 * It does not choose risk, grant tools, or accept instructions found in documents.
 */
final class InstructionAnalyzer
{
    /** @var array<string, int> */
    private const NUMBERS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14,
        'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18,
        'isa' => 1, 'dalawa' => 2, 'tatlo' => 3, 'apat' => 4, 'lima' => 5,
        'anim' => 6, 'pito' => 7, 'walo' => 8, 'siyam' => 9, 'sampu' => 10,
    ];

    public function __construct(private readonly DateRangeParser $dates) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function analyze(array $input): AnalysisResult
    {
        $text = trim((string) ($input['instruction'] ?? ''));
        $draft = is_array($input['draft_parameters'] ?? null) ? $input['draft_parameters'] : [];
        $operations = is_array($input['operations'] ?? null) ? $input['operations'] : [];
        $knowledge = is_array($input['knowledge'] ?? null) ? $input['knowledge'] : [];
        $referenceAt = (string) ($input['reference_at'] ?? now()->toIso8601String());
        $timezone = (string) ($input['timezone'] ?? 'Asia/Manila');
        $draftIntent = isset($input['draft_intent']) ? (string) $input['draft_intent'] : null;
        $hasOpenDraft = (bool) ($input['has_open_draft'] ?? false);

        $knowledgeText = implode("\n", array_map(static fn ($item): string => is_string($item) ? $item : '', $knowledge));
        $injection = $this->injection($text) || $this->injection($knowledgeText);

        $parameters = $this->aliases($draft);
        $extracted = $this->extract($text);
        foreach ($extracted as $key => $value) {
            if ($value !== null && $value !== '') {
                $parameters[$key] = $value;
            }
        }

        $parsed = $this->dates->parse($text, $referenceAt, $timezone);
        $mergedDates = $this->dates->merge($parameters, $parsed, $text);
        if ($mergedDates['arrival'] !== null) {
            $parameters['arrival'] = $mergedDates['arrival'];
        }
        if ($mergedDates['departure'] !== null) {
            $parameters['departure'] = $mergedDates['departure'];
        }

        $ambiguities = [];
        if ($mergedDates['ambiguous'] && $mergedDates['reason'] !== null) {
            $ambiguities[] = $mergedDates['reason'];
        }
        if (($extracted['guest_conflict'] ?? false) === true) {
            $ambiguities[] = 'More than one guest count was given. How many guests should the booking use?';
            unset($parameters['guests']);
        }
        if (isset($parameters['guests']) && ((int) $parameters['guests'] < 1 || (int) $parameters['guests'] > 18)) {
            $ambiguities[] = 'Guest count must be from 1 to 18. Eight guests are included in the base rate.';
            unset($parameters['guests']);
        }

        $intent = $this->intent($text, $parameters, $operations, $draftIntent, $hasOpenDraft);
        $supported = $intent === 'draft.cancel' || isset($operations[$intent]);
        if (! $supported) {
            $intent = 'unsupported';
        }

        if ($intent === 'booking.update' && $this->mutableFields($parameters) === []) {
            $ambiguities[] = 'What should change on this booking?';
        }

        $missing = $supported && $intent !== 'draft.cancel'
            ? $this->missing($intent, $parameters, $operations)
            : [];

        $candidates = match ($intent) {
            'booking.create' => array_values(array_filter(
                ['booking.check_availability', 'booking.create'],
                static fn (string $name): bool => isset($operations[$name]),
            )),
            'unsupported', 'draft.cancel' => [],
            default => [$intent],
        };

        return new AnalysisResult(
            intent: $intent,
            parameters: $this->publicParameters($parameters),
            missingFields: $missing,
            ambiguities: array_values(array_unique($ambiguities)),
            candidateOperations: $candidates,
            requiresValidation: $supported && $intent !== 'draft.cancel' && ! (($operations[$intent]['read_only'] ?? false) === true),
            supported: $supported,
            extractionConsistent: $ambiguities === [],
            cancelDraft: $intent === 'draft.cancel',
            promptInjectionDetected: $injection,
            isQuestion: preg_match('/\b(is|are|what|how|can|available)\b/i', $text) === 1,
            analyzer: 'deterministic',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $operations
     */
    public function applyOverrides(AnalysisResult $result, array $overrides, array $operations): AnalysisResult
    {
        $parameters = $result->parameters;
        foreach ($this->aliases($overrides) as $key => $value) {
            if ($value !== null && $value !== '') {
                $parameters[$key] = $key === 'guests' ? (int) $value : trim((string) $value);
            }
        }
        if (isset($parameters['email'])) {
            $parameters['email'] = strtolower((string) $parameters['email']);
        }

        $missing = $result->supported && $result->intent !== 'draft.cancel'
            ? $this->missing($result->intent, $parameters, $operations)
            : [];

        return $result->withFields($parameters, $missing, $result->ambiguities);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $operations
     * @return list<string>
     */
    public function missing(string $intent, array $parameters, array $operations): array
    {
        $required = $operations[$intent]['required'] ?? [];
        $missing = [];
        foreach ($required as $field) {
            if (! isset($parameters[$field]) || $parameters[$field] === '' || $parameters[$field] === null) {
                $missing[] = (string) $field;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $operations
     */
    private function intent(string $text, array $parameters, array $operations, ?string $draftIntent, bool $hasOpenDraft): string
    {
        $has = static fn (string $name): bool => isset($operations[$name]);

        if ($hasOpenDraft && preg_match('/\b(never mind|nevermind|forget it|cancel that|cancel this request|stop)\b/i', $text) === 1
            && preg_match('/\bbooking reference\b/i', $text) !== 1) {
            return 'draft.cancel';
        }
        if (preg_match('/\b(delete|wipe|destroy|drop)\b.{0,48}\b(all|every|database|customers?)\b/i', $text) === 1) {
            return 'unsupported';
        }
        if ($has('booking.cancel') && preg_match('/\b(cancel|kanselahin)\b/i', $text) === 1
            && (isset($parameters['reference']) || preg_match('/\bbooking\b/i', $text) === 1)) {
            return 'booking.cancel';
        }
        if ($has('booking.get') && preg_match('/\b(show|find|lookup|status of|details of)\b/i', $text) === 1
            && (isset($parameters['reference']) || preg_match('/\bbooking\b/i', $text) === 1)
            && preg_match('/\b(book|reserve|create)\b/i', $text) !== 1) {
            return 'booking.get';
        }
        if ($has('booking.check_availability')
            && preg_match('/\b(available|availability|bakante)\b/i', $text) === 1
            && preg_match('/\b(book|reserve|create|gumawa|mag-?book)\b/i', $text) !== 1) {
            return 'booking.check_availability';
        }
        if ($has('booking.update') && isset($parameters['reference'])
            && preg_match('/\b(update|modify|baguhin)\b/i', $text) === 1) {
            return 'booking.update';
        }
        if ($has('booking.create') && preg_match('/\b(book|reserve|reservation|create a booking|gumawa ng booking|mag-?book)\b/i', $text) === 1) {
            return 'booking.create';
        }
        if ($draftIntent === 'draft.cancel') {
            return 'draft.cancel';
        }
        if (is_string($draftIntent) && ($draftIntent === 'unsupported' || isset($operations[$draftIntent]))) {
            return $draftIntent;
        }

        return 'unsupported';
    }

    /**
     * @return array<string, mixed>
     */
    private function extract(string $text): array
    {
        $found = ['guest_conflict' => false];
        $words = implode('|', array_keys(self::NUMBERS));

        if (preg_match('/\b(\d{1,2}|'.$words.')\s+or\s+(\d{1,2}|'.$words.')\b(?:\s+\w+){0,2}\s+(?:guests?|pax|people|bisita)\b/i', $text) === 1) {
            $found['guest_conflict'] = true;
        } elseif (preg_match('/\b(\d{1,2}|'.$words.')(?:\s+(?:adult|extra|na))?\s+(?:guests?|pax|people|persons?|bisita)\b/i', $text, $guest) === 1) {
            $token = strtolower($guest[1]);
            $found['guests'] = is_numeric($token) ? (int) $token : self::NUMBERS[$token];
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $email) === 1) {
            $found['email'] = strtolower($email[0]);
        }
        if (preg_match('/(\+63\s?\d{10}|09\d{2}[\s\-]?\d{3}[\s\-]?\d{4})/', $text, $phone) === 1) {
            $found['mobile'] = preg_replace('/[\s\-]/', '', $phone[1]) ?? $phone[1];
        }
        if (preg_match('/\b(?:booking|reference|ref)\s+#?([0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}|[0-9a-fA-F]{8})\b/', $text, $reference) === 1) {
            $found['reference'] = strtolower($reference[1]);
        } elseif (preg_match('/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/', $text, $uuid) === 1) {
            $found['reference'] = strtolower($uuid[0]);
        }

        if (preg_match('/\b(?:for|para kay|guest(?:\s+name)?(?:\s+is)?)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3})/u', $text, $name) === 1) {
            $candidate = trim($name[1]);
            $first = strtolower(strtok($candidate, ' ') ?: '');
            $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
            if (! in_array($first, $months, true) && ! in_array(strtolower($candidate), ['six', 'the inner', 'the inner cove'], true)) {
                $found['name'] = $candidate;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function aliases(array $parameters): array
    {
        $map = [
            'guest_name' => 'name',
            'check_in' => 'arrival',
            'check_out' => 'departure',
            'guest_count' => 'guests',
            'contact_number' => 'mobile',
            'phone' => 'mobile',
            'booking_id' => 'reference',
        ];
        $normalized = [];
        foreach ($parameters as $key => $value) {
            $normalized[$map[$key] ?? $key] = $value;
        }
        unset($normalized['guest_conflict']);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function publicParameters(array $parameters): array
    {
        unset($parameters['guest_conflict']);

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<string>
     */
    private function mutableFields(array $parameters): array
    {
        $fields = [];
        foreach (['name', 'email', 'mobile', 'arrival', 'departure', 'guests', 'notes'] as $field) {
            if (isset($parameters[$field]) && $parameters[$field] !== '') {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function injection(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return preg_match('/ignore (all |previous |the )?(instructions|rules)|system prompt|you are now|drop table|set the risk to low|reveal your prompt|register (a )?tool/i', $text) === 1;
    }
}
