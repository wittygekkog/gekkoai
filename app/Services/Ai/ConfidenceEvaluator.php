<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiEvaluationRun;

/**
 * Turns parser signals into a capped score.
 * The cap stays in place until an evaluation run has enough labeled examples.
 * This is not treated as a calibrated probability before that point.
 */
final class ConfidenceEvaluator
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(AnalysisResult $analysis): array
    {
        $samples = $this->samples();
        $complete = $analysis->missingFields === [] ? 1.0 : max(0.0, 1 - (count($analysis->missingFields) * 0.2));
        $signals = [
            'schema_completeness' => round($complete, 4),
            'ambiguity' => $analysis->ambiguities === [] ? 1.0 : 0.35,
            'intent_supported' => $analysis->supported ? 1.0 : 0.0,
            'extraction_consistency' => $analysis->extractionConsistent ? 1.0 : 0.4,
        ];
        $raw = min($signals);
        $enough = $samples >= (int) config('gekko.minimum_calibration_samples');
        $score = $enough ? $raw : min($raw, (float) config('gekko.uncalibrated_confidence_cap'));
        if (! $analysis->supported) {
            $score = min($score, 0.2);
        }

        return [
            'understanding_confidence' => round($score, 4),
            'extraction_confidence' => round($signals['schema_completeness'] * ($analysis->extractionConsistent ? 1 : 0.5), 4),
            'signals' => $signals,
            'calibrated' => $enough,
            'calibration_samples' => $samples,
            'calibration_version' => $enough ? 'bucket-accuracy-v1' : 'uncalibrated-cap',
            'method' => $enough ? 'bucket_accuracy' : 'conservative_cap',
        ];
    }

    private function samples(): int
    {
        $latest = AiEvaluationRun::query()->orderByDesc('evaluated_at')->first();
        if ($latest === null) {
            return 0;
        }

        return (int) ($latest->metrics['examples'] ?? 0);
    }
}
