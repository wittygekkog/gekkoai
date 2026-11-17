<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Admin;
use App\Models\AiApproval;
use App\Models\AiClarification;
use App\Models\AiConversation;
use App\Models\AiEscalation;
use App\Models\AiMessage;
use App\Models\AiOperationExecution;
use App\Models\AiOperationRequest;
use App\Models\AiOperationStep;
use App\Models\Tenant;
use App\Models\TenantOperation;
use App\Services\AccessService;
use App\Services\Ai\AnalysisResult;
use App\Services\Ai\ConfidenceEvaluator;
use App\Services\Ai\InstructionAnalyzer;
use App\Services\AuditLogger;
use App\Services\Connectors\ConnectorGateway;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;
use WittyGekko\SbuConnector\Exceptions\ConnectorTimeoutException;
use WittyGekko\SbuConnector\Registry\OperationRegistry;
use WittyGekko\SbuConnector\Security\RequestFingerprint;

/**
 * Turns an admin instruction into a validated, approved, or clarified operation.
 * The model proposes. This class authorizes and the connector performs the write.
 */
final class InstructionPipeline
{
    public function __construct(
        private readonly InstructionAnalyzer $analyzer,
        private readonly ConfidenceEvaluator $confidence,
        private readonly ExecutionPolicy $policy,
        private readonly OperationStateMachine $states,
        private readonly ConnectorGateway $gateway,
        private readonly AccessService $access,
        private readonly AuditLogger $audit,
        private readonly KnowledgeService $knowledge,
        private readonly OperationRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $structured
     */
    public function handle(AiConversation $conversation, Admin $admin, string $instruction, array $structured = []): AiOperationRequest
    {
        $this->access->assert($admin, $conversation->tenant, 'ai.chat.use');
        $instruction = trim($instruction);
        if ($instruction === '' && $structured === []) {
            abort(422, 'Enter an instruction.');
        }

        $readyId = DB::transaction(function () use ($conversation, $admin, $instruction, $structured): ?string {
            $this->say($conversation, 'user', 'user', $instruction !== '' ? $instruction : 'Submitted the requested details.');
            if ($conversation->title === 'New conversation' && $instruction !== '') {
                $conversation->title = Str::limit($instruction, 80, '');
                $conversation->save();
            }

            $draft = $this->openDraft($conversation);
            $created = $draft === null;
            if ($draft === null) {
                $draft = $this->newRequest($conversation, $admin, $instruction);
            } else {
                $draft->clarifications()->where('status', 'open')->update([
                    'status' => 'answered',
                    'response' => $instruction,
                    'updated_at' => now(),
                ]);
                $this->states->transition($draft, 'analyzing', 'A follow-up instruction was received.', $admin);
            }

            $operations = $this->operationMap($conversation->tenant);
            $analysis = $this->analyzer->analyze([
                'instruction' => $instruction,
                'draft_parameters' => $draft->parameters ?? [],
                'draft_intent' => $created ? null : $draft->intent,
                'reference_at' => now()->toIso8601String(),
                'timezone' => $conversation->tenant->timezone ?: 'Asia/Manila',
                'operations' => $operations,
                'has_open_draft' => ! $created,
                'knowledge' => $this->knowledge->snippets($conversation->tenant_id, $instruction),
            ]);
            if ($structured !== []) {
                $analysis = $this->analyzer->applyOverrides($analysis, $structured, $operations);
            }

            if ($analysis->cancelDraft) {
                $this->states->transition($draft, 'cancelled', 'The admin cancelled the draft.', $admin);
                $this->say($conversation, 'assistant', 'assistant', 'I cancelled this draft. Nothing was sent to the business unit.');
                $this->audit->log($conversation->tenant_id, $admin->id, 'operation.cancelled', 'ai_operation_request', $draft->id, $draft->correlation_id, []);

                return null;
            }

            $duplicate = $this->completedDuplicate($conversation, $analysis);
            if ($duplicate !== null) {
                $this->states->transition($draft, 'cancelled', 'The same instruction was already completed.', $admin);
                $reference = $duplicate->executions()->latest('created_at')->first()?->response_data['data']['short_reference'] ?? null;
                $this->say($conversation, 'assistant', 'assistant', 'That request was already completed'.($reference ? ' with reference '.$reference : '').'. I did not create another booking.');

                return null;
            }

            $this->remember($draft, $analysis, $instruction, $created);
            $score = $this->confidence->evaluate($analysis);
            $draft->confidence_score = $score['understanding_confidence'];
            $draft->confidence_metadata = $score;
            $draft->calibration_version = $score['calibration_version'];
            $draft->analyzer = $analysis->analyzer;
            $draft->save();

            if (! $analysis->supported) {
                $this->escalate($draft, $conversation, $admin, 'unsupported', 'I cannot perform that request with the operations enabled for this business unit.');

                return null;
            }

            $meta = $this->meta($conversation->tenant, $analysis->intent);
            if ($meta === null || $conversation->tenant->connector === null) {
                $this->escalate($draft, $conversation, $admin, 'unsupported', 'That operation is not enabled for this business unit.');

                return null;
            }

            $draft->risk_level = $meta['risk'];
            $draft->save();

            $availability = null;
            if ($analysis->intent === 'booking.create' && $this->hasDates($analysis->parameters)) {
                $availability = $this->runRead($draft, $admin, 'booking.check_availability', [
                    'arrival' => $analysis->parameters['arrival'],
                    'departure' => $analysis->parameters['departure'],
                    'guests' => $analysis->parameters['guests'],
                ]);
            }

            if ($analysis->missingFields !== [] || $analysis->ambiguities !== []) {
                $this->clarify($draft, $conversation, $admin, $analysis, $availability);

                return null;
            }

            if (is_array($availability) && ($availability['available'] ?? true) === false) {
                $this->states->transition($draft, 'failed', 'The dates are not available.', $admin);
                $this->say($conversation, 'assistant', 'execution_result', 'Those dates are already reserved. Tell me different dates and I will check again. No booking was created.', [
                    'kind' => 'unavailable',
                    'availability' => $availability,
                    'operation_request_id' => $draft->id,
                ]);

                return null;
            }

            $decision = $this->policy->decide($admin, $conversation->tenant, $analysis, $meta, $score);
            if (! $decision['authorized']) {
                $this->escalate($draft, $conversation, $admin, 'unauthorized', 'You are not allowed to run this operation.');

                return null;
            }

            $this->states->transition($draft, 'planning', 'The operation plan is ready.', $admin);
            $this->states->transition($draft, 'validating', 'Required details are present.', $admin);

            if ($meta['read_only'] && $decision['auto_execute_read']) {
                $this->finishRead($draft, $conversation, $admin, $analysis);

                return null;
            }

            if ($decision['auto_execute_write']) {
                $draft->execution_mode = 'automatic';
                $this->ensureKey($draft);
                $this->states->transition($draft, 'ready', 'Automatic execution is allowed for this read-safe policy.', $admin);

                return $draft->id;
            }

            $this->requestApproval($draft, $conversation, $admin, $analysis, $availability);

            return null;
        });

        if ($readyId === null) {
            return $conversation->operations()->first() ?? new AiOperationRequest();
        }

        return $this->executeWrite(AiOperationRequest::query()->findOrFail($readyId), $admin, 'automatic');
    }

    public function approve(AiApproval $approval, Admin $admin, ?string $reason): AiOperationRequest
    {
        $request = AiOperationRequest::query()->findOrFail($approval->operation_request_id);
        $request->load('tenant.connector', 'conversation');
        $this->access->assert($admin, $request->tenant, 'ai.operations.approve');

        DB::transaction(function () use ($approval, $admin, $request, $reason): void {
            $locked = AiApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                abort(409, 'This approval is no longer pending.');
            }
            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                $locked->status = 'expired';
                $locked->save();
                abort(409, 'This approval has expired.');
            }
            $current = $this->hash($request->fresh()->parameters ?? []);
            if (! hash_equals((string) $locked->parameter_hash, $current)) {
                $locked->status = 'invalidated';
                $locked->decision_reason = 'The request changed after this approval was created.';
                $locked->save();
                abort(409, 'The request changed, so this approval is no longer valid.');
            }

            $locked->status = 'approved';
            $locked->approved_by = $admin->id;
            $locked->decision_reason = $reason;
            $locked->save();
            $fresh = $request->fresh();
            $this->ensureKey($fresh);
            $this->states->transition($fresh, 'ready', 'Approved by '.$admin->username.'.', $admin);
        });

        return $this->executeWrite($request->fresh(), $admin, 'approved');
    }

    public function reject(AiApproval $approval, Admin $admin, string $reason): void
    {
        $request = AiOperationRequest::query()->findOrFail($approval->operation_request_id);
        $this->access->assert($admin, $request->tenant, 'ai.operations.reject');
        if ($approval->status !== 'pending') {
            abort(409, 'This approval is no longer pending.');
        }
        $approval->status = 'rejected';
        $approval->approved_by = $admin->id;
        $approval->decision_reason = $reason;
        $approval->save();
        $this->states->transition($request, 'cancelled', 'An approver rejected the operation.', $admin);
        $this->say($request->conversation, 'assistant', 'assistant', 'The operation was rejected. '.$reason);
        $this->audit->log($request->tenant_id, $admin->id, 'operation.rejected', 'ai_operation_request', $request->id, $request->correlation_id, []);
    }

    public function reconcile(AiOperationRequest $request, Admin $admin): AiOperationRequest
    {
        $record = $this->gateway->idempotency()->findByKey($request->tenant_id, (string) $request->idempotency_key);
        if ($record !== null && $record->status === 'completed' && is_array($record->result)) {
            $result = OperationResult::fromArray($record->result);
            if ($request->status === 'unknown_outcome') {
                $this->states->transition($request, 'verifying', 'The connector had already saved the operation.', $admin);
            }
            $this->storeExecution($request, $result, true);
            if ($request->fresh()->status !== 'completed') {
                $this->states->transition($request->fresh(), 'completed', 'Reconciled with the saved result.', $admin);
            }
            $this->describeResult($request->conversation, $result, true);

            return $request->fresh();
        }

        if ($request->status !== 'escalated') {
            $this->escalate($request, $request->conversation, $admin, 'unknown_outcome', 'The connector did not confirm the result. It was not run again.');
        }

        return $request->fresh();
    }

    private function executeWrite(AiOperationRequest $request, Admin $admin, string $mode): AiOperationRequest
    {
        $request->load('tenant.connector', 'conversation', 'initiator');
        $initiator = $request->initiator;
        if (! $initiator instanceof Admin || ! $this->access->allows($initiator, $request->tenant, 'ai.operations.execute')) {
            $this->states->transition($request, 'failed', 'The person who started this request can no longer run it.', $admin);
            $this->say($request->conversation, 'assistant', 'execution_result', 'Execution stopped because permission was removed. Nothing new was written.');

            return $request->fresh();
        }

        $this->ensureKey($request->fresh());
        $request = $request->fresh();
        $request->execution_mode = $mode;
        $request->save();
        $this->states->transition($request, 'executing', 'The connector is performing the operation.', $admin);

        try {
            $result = $this->gateway->execute(
                $request->tenant->connector,
                (string) $request->intent,
                $request->parameters ?? [],
                $this->context($request, (string) $request->idempotency_key),
                true,
            );
        } catch (ConnectorTimeoutException) {
            $this->states->transition($request->fresh(), 'unknown_outcome', 'The connector timed out after the request was sent.', $admin);

            return $this->reconcile($request->fresh(), $admin);
        } catch (BusinessRuleException $exception) {
            $this->states->transition($request->fresh(), 'failed', $exception->getMessage(), $admin);
            $this->say($request->conversation, 'assistant', 'execution_result', $exception->getMessage().' No booking was created.');

            return $request->fresh();
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->states->transition($request->fresh(), 'failed', $message, $admin);
            $this->say($request->conversation, 'assistant', 'execution_result', $message);

            return $request->fresh();
        }

        $this->states->transition($request->fresh(), 'verifying', 'Checking the saved record.', $admin);
        $verified = $this->verify($request->fresh(), $result);
        $this->storeExecution($request->fresh(), $result, $verified);
        if (! $verified) {
            $this->states->transition($request->fresh(), 'unknown_outcome', 'The saved record could not be confirmed.', $admin);

            return $this->reconcile($request->fresh(), $admin);
        }

        $this->states->transition($request->fresh(), 'completed', 'The business unit confirmed the result.', $admin);
        $this->describeResult($request->conversation, $result, false);
        $this->audit->log($request->tenant_id, $admin->id, 'operation.completed', 'ai_operation_request', $request->id, $request->correlation_id, [
            'intent' => $request->intent,
            'reference' => $result->data['short_reference'] ?? null,
        ]);

        return $request->fresh();
    }

    private function verify(AiOperationRequest $request, OperationResult $result): bool
    {
        $reference = $result->data['reference'] ?? null;
        if (! is_string($reference) || $reference === '') {
            return $result->success;
        }
        try {
            $check = $this->gateway->execute(
                $request->tenant->connector,
                'booking.get',
                ['reference' => $reference],
                $this->context($request, $request->id.'-verify'),
                false,
            );
        } catch (BusinessRuleException) {
            return false;
        }

        return ($check->data['reference'] ?? null) === $reference && ($check->data['status'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>|null  $availability
     */
    private function clarify(AiOperationRequest $request, AiConversation $conversation, Admin $admin, AnalysisResult $analysis, ?array $availability): void
    {
        $last = $request->clarifications()->orderByDesc('round_number')->first();
        $round = ($last->round_number ?? 0) + 1;
        $max = max(1, (int) (getSetting('ai-max-clarification-rounds', '3') ?? 3));
        $same = $last instanceof AiClarification && $last->expected_fields === $analysis->missingFields;
        if ($round > $max || ($same && $last->round_number >= 2)) {
            $this->escalate($request, $conversation, $admin, 'clarification_exhausted', 'I still do not have a clear answer, so I escalated this to a reviewer.');

            return;
        }

        [$question, $fields] = $this->question($analysis);
        if (is_array($availability) && ($availability['available'] ?? true) === false) {
            $question = 'Those dates are already reserved. '.$question;
        } elseif (is_array($availability) && ($availability['available'] ?? false) === true) {
            $question = 'The retreat is free for those dates ('.$availability['calculated'].' before any seasonal adjustment). '.$question;
        }

        $clarification = new AiClarification();
        $clarification->tenant_id = $request->tenant_id;
        $clarification->conversation_id = $conversation->id;
        $clarification->operation_request_id = $request->id;
        $clarification->question = $question;
        $clarification->expected_fields = $fields;
        $clarification->round_number = $round;
        $clarification->status = 'open';
        $clarification->save();

        $this->states->transition($request, 'awaiting_clarification', 'More information is required.', $admin);
        $this->say($conversation, 'assistant', 'clarification', $question, [
            'kind' => 'clarification',
            'parameters' => $analysis->parameters,
            'missing_fields' => $analysis->missingFields,
            'availability' => $availability,
            'operation_request_id' => $request->id,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $availability
     */
    private function requestApproval(AiOperationRequest $request, AiConversation $conversation, Admin $admin, AnalysisResult $analysis, ?array $availability): void
    {
        $this->ensureKey($request);
        $request->approvals()->where('status', 'pending')->update([
            'status' => 'invalidated',
            'decision_reason' => 'A new approval snapshot replaced this one.',
            'updated_at' => now(),
        ]);

        $snapshot = [
            'operation' => $request->intent,
            'parameters' => $request->parameters,
            'parameter_hash' => $request->parameter_hash,
            'idempotency_key' => $request->idempotency_key,
            'risk_level' => $request->risk_level,
            'quote' => $availability,
        ];
        $approval = new AiApproval();
        $approval->tenant_id = $request->tenant_id;
        $approval->operation_request_id = $request->id;
        $approval->requested_by = $admin->id;
        $approval->status = 'pending';
        $approval->approval_snapshot = $snapshot;
        $approval->parameter_hash = (string) $request->parameter_hash;
        $approval->expires_at = now()->addDay();
        $approval->save();

        $this->states->transition($request, 'awaiting_approval', 'A person must approve this before it runs.', $admin);
        $total = is_array($availability) ? (string) ($availability['calculated'] ?? '') : '';
        $text = 'I have the details for '.$analysis->intent.'. This is a '.$request->risk_level.'-risk operation, so it is waiting for approval.';
        if ($total !== '') {
            $text .= ' Quoted total '.$total.'. '.$availability['rate_note'];
        }
        $this->say($conversation, 'assistant', 'approval_request', $text, [
            'kind' => 'approval',
            'approval_id' => $approval->id,
            'parameters' => $request->parameters,
            'quote' => $availability,
            'risk_level' => $request->risk_level,
            'operation_request_id' => $request->id,
        ]);
    }

    private function finishRead(AiOperationRequest $request, AiConversation $conversation, Admin $admin, AnalysisResult $analysis): void
    {
        $result = $this->runRead($request, $admin, $analysis->intent, $analysis->parameters);
        $ok = ($result['success'] ?? false) === true;
        if (! $ok) {
            $this->states->transition($request, 'failed', (string) ($result['message'] ?? 'The read failed.'), $admin);
            $this->say($conversation, 'assistant', 'execution_result', (string) ($result['message'] ?? 'The lookup did not succeed.'));

            return;
        }
        $this->states->transition($request->fresh(), 'ready', 'Read is ready.', $admin);
        $this->states->transition($request->fresh(), 'executing', 'Reading from the connector.', $admin);
        $this->states->transition($request->fresh(), 'verifying', 'Read completed.', $admin);
        $this->states->transition($request->fresh(), 'completed', 'The read finished.', $admin);
        $request->execution_mode = 'automatic';
        $request->save();
        $summary = $analysis->intent === 'booking.check_availability'
            ? (($result['available'] ?? false) ? 'The retreat is available. '.$result['availability'].' Quote '.$result['calculated'].'.' : 'The retreat is not available. '.$result['availability'])
            : 'Booking '.$result['short_reference'].' is '.$result['status'].'.';
        $this->say($conversation, 'assistant', 'execution_result', $summary, ['kind' => 'read', 'result' => $result, 'operation_request_id' => $request->id]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function runRead(AiOperationRequest $request, Admin $admin, string $operation, array $parameters): array
    {
        try {
            $result = $this->gateway->execute(
                $request->tenant->connector,
                $operation,
                $parameters,
                $this->context($request, $request->id.'-'.$operation),
                false,
            );
            $data = $result->data;
            $data['success'] = $result->success;
        } catch (BusinessRuleException $exception) {
            $data = ['success' => false, 'message' => $exception->getMessage()];
        } catch (ValidationException $exception) {
            $data = ['success' => false, 'message' => (string) collect($exception->errors())->flatten()->first()];
        }

        $step = new AiOperationStep();
        $step->operation_request_id = $request->id;
        $step->step_number = (int) $request->steps()->count() + 1;
        $step->operation_name = $operation;
        $step->parameters = $parameters;
        $step->status = ($data['success'] ?? false) ? 'completed' : 'failed';
        $step->result = $data;
        $step->started_at = now();
        $step->completed_at = now();
        $step->save();

        return $data;
    }

    private function escalate(AiOperationRequest $request, AiConversation $conversation, Admin $admin, string $reason, string $message): void
    {
        if (! in_array($request->status, ['escalated', 'cancelled', 'completed'], true)) {
            $this->states->transition($request, 'escalated', $message, $admin);
        }
        $escalation = new AiEscalation();
        $escalation->tenant_id = $request->tenant_id;
        $escalation->conversation_id = $conversation->id;
        $escalation->operation_request_id = $request->id;
        $escalation->reason = $reason;
        $escalation->priority = $reason === 'unauthorized' ? 'high' : 'normal';
        $escalation->status = 'open';
        $escalation->context = [
            'intent' => $request->intent,
            'parameters' => $request->parameters,
            'missing_fields' => $request->missing_fields,
            'confidence' => $request->confidence_metadata,
        ];
        $escalation->save();
        $this->say($conversation, 'assistant', 'escalation', $message, [
            'kind' => 'escalation',
            'escalation_id' => $escalation->id,
            'reason' => $reason,
        ]);
        $this->audit->log($request->tenant_id, $admin->id, 'operation.escalated', 'ai_operation_request', $request->id, $request->correlation_id, ['reason' => $reason]);
    }

    private function newRequest(AiConversation $conversation, Admin $admin, string $instruction): AiOperationRequest
    {
        $request = new AiOperationRequest();
        $request->tenant_id = $conversation->tenant_id;
        $request->conversation_id = $conversation->id;
        $request->initiated_by = $admin->id;
        $request->original_instruction = $instruction;
        $request->status = 'received';
        $request->correlation_id = (string) Str::uuid();
        $request->parameters = [];
        $request->save();
        $this->states->record($request, null, 'received', 'Instruction received.', $admin);
        $this->states->transition($request, 'analyzing', 'The instruction is being read.', $admin);

        return $request->fresh();
    }

    private function remember(AiOperationRequest $request, AnalysisResult $analysis, string $instruction, bool $created): void
    {
        $previous = $request->parameter_hash;
        $request->intent = $analysis->intent;
        $request->parameters = $analysis->parameters;
        $request->missing_fields = $analysis->missingFields;
        $request->ambiguities = $analysis->ambiguities;
        $hash = $this->hash($analysis->parameters);
        if (is_string($previous) && ! hash_equals($previous, $hash)) {
            $request->approvals()->where('status', 'pending')->update([
                'status' => 'invalidated',
                'decision_reason' => 'Parameters changed after approval was requested.',
                'updated_at' => now(),
            ]);
        }
        $request->parameter_hash = $hash;
        if ($created) {
            $request->original_instruction = $instruction;
        }
        $request->save();
    }

    private function ensureKey(AiOperationRequest $request): void
    {
        $hash = $this->hash($request->parameters ?? []);
        $request->parameter_hash = $hash;
        $request->idempotency_key = 'op-'.$request->id.'-'.substr($hash, 0, 24);
        $request->save();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function hash(array $parameters): string
    {
        return hash('sha256', json_encode(RequestFingerprint::canonicalize($parameters), JSON_THROW_ON_ERROR));
    }

    private function context(AiOperationRequest $request, string $idempotencyKey): OperationContext
    {
        return new OperationContext(
            requestId: $request->id,
            operationId: $request->id,
            tenantId: $request->tenant_id,
            idempotencyKey: $idempotencyKey,
            actorId: (string) $request->initiated_by,
            approvalReference: $request->approvals()->where('status', 'approved')->value('id'),
            correlationId: $request->correlation_id,
        );
    }

    private function openDraft(AiConversation $conversation): ?AiOperationRequest
    {
        return $conversation->operations()->whereIn('status', AiOperationRequest::OPEN_STATUSES)->latest('created_at')->first();
    }

    private function completedDuplicate(AiConversation $conversation, AnalysisResult $analysis): ?AiOperationRequest
    {
        if (! $analysis->supported || $analysis->missingFields !== []) {
            return null;
        }

        return $conversation->operations()
            ->where('status', 'completed')
            ->where('intent', $analysis->intent)
            ->where('parameter_hash', $this->hash($analysis->parameters))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function hasDates(array $parameters): bool
    {
        return ! empty($parameters['arrival']) && ! empty($parameters['departure']) && ! empty($parameters['guests']);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function question(AnalysisResult $analysis): array
    {
        $fields = $analysis->missingFields;
        $lines = $analysis->ambiguities;
        if (in_array('email', $fields, true) && in_array('mobile', $fields, true)) {
            $name = (string) ($analysis->parameters['name'] ?? 'the guest');
            $lines[] = 'What email address and mobile number should we use for '.$name.'?';
            $fields = array_values(array_diff($fields, ['email', 'mobile']));
        }
        $labels = [
            'name' => 'What is the guest\'s full name?',
            'email' => 'What email address should we use?',
            'mobile' => 'What mobile number should we use?',
            'arrival' => 'What is the arrival date? Check-in is 14:00.',
            'departure' => 'What is the departure date? Check-out is 12:00.',
            'guests' => 'How many guests?',
            'reference' => 'Which booking reference should I use?',
        ];
        foreach ($fields as $field) {
            $lines[] = $labels[$field] ?? 'Please provide '.$field.'.';
        }

        return [implode(' ', $lines), $analysis->missingFields];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function operationMap(Tenant $tenant): array
    {
        $map = [];
        $rows = TenantOperation::query()->where('tenant_id', $tenant->id)->where('enabled', true)->get();
        foreach ($rows as $row) {
            if (! $this->registry->has($row->operation_name)) {
                continue;
            }
            $definition = $this->registry->get($row->operation_name);
            $risk = $this->higherRisk($definition->risk, (string) $row->risk_level);
            $map[$row->operation_name] = [
                'required' => $definition->requiredFields,
                'risk' => $risk,
                'read_only' => $definition->readOnly,
                'approval_required' => $definition->approvalRequired || (bool) $row->approval_required || in_array($risk, ['high', 'critical'], true),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function meta(Tenant $tenant, string $intent): ?array
    {
        $map = $this->operationMap($tenant);

        return $map[$intent] ?? null;
    }

    private function higherRisk(string $left, string $right): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        return ($rank[$left] ?? 4) >= ($rank[$right] ?? 4) ? $left : $right;
    }

    private function storeExecution(AiOperationRequest $request, OperationResult $result, bool $verified): void
    {
        $execution = new AiOperationExecution();
        $execution->operation_request_id = $request->id;
        $execution->tenant_id = $request->tenant_id;
        $execution->connector_id = $request->tenant->connector?->id;
        $execution->external_operation_id = is_string($result->data['booking_id'] ?? null) ? $result->data['booking_id'] : $request->id;
        $execution->request_fingerprint = $request->parameter_hash;
        $execution->status = $result->success ? 'completed' : 'failed';
        $execution->response_data = $result->toArray();
        $execution->error_code = $result->errorCode;
        $execution->executed_at = now();
        $execution->verified_at = $verified ? now() : null;
        $execution->save();
    }

    private function describeResult(AiConversation $conversation, OperationResult $result, bool $reconciled): void
    {
        $short = (string) ($result->data['short_reference'] ?? '');
        $text = $reconciled
            ? 'The earlier request had already been saved. Reference '.$short.'. I did not create a second booking.'
            : 'The Inner Cove confirmed the booking. Reference '.$short.'. Status: '.($result->data['status'] ?? 'pending').'. Total '.($result->data['total'] ?? '').'.';
        $this->say($conversation, 'assistant', 'execution_result', trim($text), [
            'kind' => 'executed',
            'result' => $result->data,
            'reconciled' => $reconciled,
        ]);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function say(AiConversation $conversation, string $role, string $type, string $content, array $structured = []): void
    {
        $message = new AiMessage();
        $message->conversation_id = $conversation->id;
        $message->tenant_id = $conversation->tenant_id;
        $message->role = $role;
        $message->message_type = $type;
        $message->content = $content;
        $message->structured_content = $structured === [] ? null : $structured;
        $message->save();
        $conversation->touch();
    }
}
