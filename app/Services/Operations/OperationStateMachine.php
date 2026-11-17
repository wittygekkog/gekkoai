<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Exceptions\IllegalTransitionException;
use App\Models\Admin;
use App\Models\AiOperationRequest;
use App\Models\AiOperationStateHistory;

/**
 * Guarded status changes. Every accepted change is stored.
 */
final class OperationStateMachine
{
    /** @var array<string, list<string>> */
    private const EDGES = [
        'received' => ['transcribing', 'analyzing', 'cancelled'],
        'transcribing' => ['analyzing', 'failed', 'cancelled'],
        'analyzing' => ['awaiting_clarification', 'planning', 'escalated', 'failed', 'cancelled'],
        'awaiting_clarification' => ['analyzing', 'escalated', 'cancelled'],
        'planning' => ['validating', 'failed', 'escalated'],
        'validating' => ['awaiting_approval', 'ready', 'awaiting_clarification', 'failed', 'escalated'],
        'awaiting_approval' => ['ready', 'analyzing', 'cancelled', 'failed', 'escalated'],
        'ready' => ['executing', 'analyzing', 'cancelled', 'failed'],
        'executing' => ['verifying', 'unknown_outcome', 'failed'],
        'verifying' => ['completed', 'unknown_outcome', 'failed'],
        'unknown_outcome' => ['verifying', 'completed', 'failed', 'escalated'],
        'completed' => [],
        'failed' => ['analyzing', 'ready', 'escalated', 'cancelled'],
        'escalated' => ['analyzing', 'cancelled'],
        'cancelled' => [],
    ];

    public function assertCan(string $from, string $to): void
    {
        if (! in_array($to, self::EDGES[$from] ?? [], true)) {
            throw new IllegalTransitionException($from, $to);
        }
    }

    public function transition(AiOperationRequest $request, string $to, string $reason, ?Admin $actor): void
    {
        $from = (string) $request->status;
        if ($from === $to) {
            return;
        }

        $this->assertCan($from, $to);
        $request->status = $to;
        $request->save();
        $this->record($request, $from, $to, $reason, $actor);
    }

    public function record(AiOperationRequest $request, ?string $from, string $to, string $reason, ?Admin $actor): void
    {
        $history = new AiOperationStateHistory();
        $history->operation_request_id = $request->id;
        $history->from_status = $from;
        $history->to_status = $to;
        $history->reason = $reason;
        $history->changed_by = $actor?->id;
        $history->created_at = now();
        $history->save();
    }
}
