<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Admin;
use App\Models\Tenant;
use App\Services\AccessService;
use App\Services\Ai\AnalysisResult;

/**
 * Decides whether a prepared operation may run by itself.
 * Risk always comes from the registry, never from the model.
 */
final class ExecutionPolicy
{
    public function __construct(private readonly AccessService $access) {}

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $confidence
     * @return array<string, mixed>
     */
    public function decide(Admin $admin, Tenant $tenant, AnalysisResult $analysis, array $meta, array $confidence): array
    {
        $authorized = $this->access->allows($admin, $tenant, 'ai.operations.execute');
        $risk = (string) $meta['risk'];
        $readOnly = (bool) $meta['read_only'];
        $approvalRequired = (bool) $meta['approval_required'] || in_array($risk, ['high', 'critical'], true);
        $score = (float) $confidence['understanding_confidence'];
        $calibrated = (bool) $confidence['calibrated'];
        $killSwitch = getSetting('ai-auto-execution-enabled', '0') === '1';
        $threshold = (float) (getSetting('ai-min-auto-confidence', '0.90') ?? '0.90');
        $clean = $analysis->supported
            && $analysis->missingFields === []
            && $analysis->ambiguities === []
            && $analysis->extractionConsistent
            && ! $analysis->promptInjectionDetected;

        $readAuto = $authorized && $readOnly && $risk === 'low' && $clean && $score >= (float) config('gekko.read_confidence_floor');
        $writeAuto = $authorized
            && ! $readOnly
            && $risk === 'medium'
            && $clean
            && $killSwitch
            && $calibrated
            && ! $approvalRequired
            && $score >= $threshold
            && $risk !== 'critical';

        return [
            'authorized' => $authorized,
            'risk' => $risk,
            'read_only' => $readOnly,
            'requires_approval' => ! $readOnly && ! $writeAuto,
            'auto_execute_read' => $readAuto,
            'auto_execute_write' => $writeAuto,
            'threshold' => $threshold,
            'kill_switch' => $killSwitch,
        ];
    }
}
