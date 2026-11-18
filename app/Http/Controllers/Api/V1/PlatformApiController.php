<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MessageRequest;
use App\Models\Admin;
use App\Models\AiApproval;
use App\Models\AiClarification;
use App\Models\AiConversation;
use App\Models\AiEscalation;
use App\Models\AiKnowledgeDocument;
use App\Models\AiLogicItem;
use App\Models\AiOperationRequest;
use App\Models\Tenant;
use App\Services\AccessService;
use App\Services\AudioIntakeService;
use App\Services\KnowledgeService;
use App\Services\LogicService;
use App\Services\Mobile\PlatformPayload;
use App\Services\RuleChatService;
use App\Services\Operations\InstructionPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Read and action endpoints for the Android app.
 * Every write is an explicit request. Loading or refreshing a screen never calls the pipeline.
 */
class PlatformApiController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly InstructionPipeline $pipeline,
        private readonly PlatformPayload $payload,
        private readonly AudioIntakeService $audio,
        private readonly LogicService $logic,
        private readonly KnowledgeService $knowledge,
        private readonly RuleChatService $rules,
    ) {}

    public function tenants(Request $request): JsonResponse
    {
        $tenants = $this->access->tenantsFor($this->admin($request))
            ->map(fn (Tenant $tenant): array => $this->payload->tenant($tenant))
            ->values();

        return $this->ok('Tenants loaded.', ['tenants' => $tenants]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $ids = $this->tenantIds($request);
        $rows = AiConversation::query()
            ->whereIn('tenant_id', $ids)
            ->latest('updated_at')
            ->paginate(50)
            ->through(fn (AiConversation $conversation): array => $this->payload->conversation($conversation));

        return $this->ok('Conversations loaded.', ['conversations' => $rows]);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid']]);
        $tenant = $this->tenant($request, $data['tenant_id']);
        $this->access->assert($this->admin($request), $tenant, 'ai.chat.use');
        $conversation = new AiConversation();
        $conversation->tenant_id = $tenant->id;
        $conversation->user_id = $this->admin($request)->id;
        $conversation->title = 'New conversation';
        $conversation->status = 'open';
        $conversation->model_provider = config('gekko.analyzer') === 'http' ? 'http' : 'deterministic';
        $conversation->model_name = config('gekko.analyzer') === 'http' ? 'configured-provider' : 'Gekko baseline parser';
        $conversation->save();

        return $this->ok('Conversation created.', ['conversation' => $this->payload->conversation($conversation)], 201);
    }

    public function showConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->guardConversation($request, $conversation);

        return $this->ok('Conversation loaded.', $this->payload->workspace($conversation));
    }

    public function message(MessageRequest $request, AiConversation $conversation): JsonResponse
    {
        $this->guardConversation($request, $conversation, 'ai.chat.use');
        $body = trim($request->string('body')->toString());
        $fields = array_filter($request->input('fields', []), static fn ($value): bool => $value !== null && $value !== '');
        $operation = $this->pipeline->handle($conversation, $this->admin($request), $body, $fields);
        $conversation->refresh();

        return $this->ok('Instruction processed.', array_merge($this->payload->workspace($conversation), [
            'operation' => $operation->exists ? $this->payload->operation($operation->fresh() ?? $operation, true) : null,
        ]));
    }

    public function audio(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->guardConversation($request, $conversation, 'ai.chat.use');
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:'.config('gekko.audio_max_kilobytes'), 'mimetypes:audio/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp4,audio/aac,audio/3gpp,audio/amr,video/webm,video/mp4'],
            'language' => ['nullable', 'string', 'in:en,tl,fil'],
        ]);
        $language = $data['language'] ?? null;
        if ($language === 'fil') {
            $language = 'tl';
        }
        $transcription = $this->audio->store($conversation, $data['audio'], $language);

        return $this->ok('Recording stored.', [
            'transcription' => $this->payload->transcription($transcription),
        ]);
    }

    public function logic(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->guardConversation($request, $conversation);
        $workspace = $this->payload->workspace($conversation);
        $operation = $workspace['latest_operation'];

        return $this->ok('Logic loaded.', [
            'conversation_id' => $conversation->id,
            'intent' => is_array($operation) ? ($operation['intent'] ?? null) : null,
            'parameters' => is_array($operation) ? ($operation['parameters'] ?? []) : [],
            'missing_fields' => is_array($operation) ? ($operation['missing_fields'] ?? []) : [],
            'confidence_score' => is_array($operation) ? ($operation['confidence_score'] ?? null) : null,
            'confidence' => is_array($operation) ? ($operation['confidence'] ?? null) : null,
            'status' => is_array($operation) ? ($operation['status'] ?? null) : null,
            'risk_level' => is_array($operation) ? ($operation['risk_level'] ?? null) : null,
            'execution_eligible' => is_array($operation) && in_array($operation['status'] ?? '', ['ready', 'awaiting_approval', 'completed'], true),
            'steps' => is_array($operation) ? ($operation['steps'] ?? []) : [],
        ]);
    }

    public function operations(Request $request): JsonResponse
    {
        $ids = $this->tenantIds($request);
        $rows = AiOperationRequest::query()
            ->with(['approvals', 'clarifications', 'executions', 'steps'])
            ->whereIn('tenant_id', $ids)
            ->latest()
            ->paginate(50)
            ->through(fn (AiOperationRequest $operation): array => $this->payload->operation($operation));

        return $this->ok('Operations loaded.', ['operations' => $rows]);
    }

    public function showOperation(Request $request, AiOperationRequest $operation): JsonResponse
    {
        $this->access->assert($this->admin($request), $operation->tenant, 'tenants.view');

        return $this->ok('Operation loaded.', ['operation' => $this->payload->operation($operation, true)]);
    }

    public function approvals(Request $request): JsonResponse
    {
        $ids = $this->tenantIds($request);
        $status = (string) $request->query('status', 'pending');
        $rows = AiApproval::query()
            ->whereIn('tenant_id', $ids)
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(50)
            ->through(fn (AiApproval $approval): array => $this->payload->approval($approval));

        return $this->ok('Approvals loaded.', ['approvals' => $rows]);
    }

    public function approve(Request $request, AiApproval $approval): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $operation = $this->pipeline->approve($approval, $this->admin($request), $data['reason'] ?? null);

        return $this->ok('Approval accepted.', ['operation' => $this->payload->operation($operation->fresh() ?? $operation, true)]);
    }

    public function reject(Request $request, AiApproval $approval): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->pipeline->reject($approval, $this->admin($request), $data['reason']);

        return $this->ok('Approval rejected.', []);
    }

    public function escalations(Request $request): JsonResponse
    {
        $ids = $this->tenantIds($request);
        $rows = AiEscalation::query()
            ->whereIn('tenant_id', $ids)
            ->latest()
            ->paginate(50)
            ->through(fn (AiEscalation $escalation): array => $this->payload->escalation($escalation));

        return $this->ok('Escalations loaded.', ['escalations' => $rows]);
    }

    public function resolveEscalation(Request $request, AiEscalation $escalation): JsonResponse
    {
        $this->access->assert($this->admin($request), $escalation->tenant, 'ai.escalations.resolve');
        $data = $request->validate(['resolution' => ['required', 'string', 'max:2000']]);
        if ($escalation->status === 'resolved') {
            return $this->ok('Escalation was already resolved.', ['escalation' => $this->payload->escalation($escalation)]);
        }
        $escalation->status = 'resolved';
        $escalation->resolution = $data['resolution'];
        $escalation->resolved_by = $this->admin($request)->id;
        $escalation->save();

        return $this->ok('Escalation resolved.', ['escalation' => $this->payload->escalation($escalation)]);
    }

    public function flags(Request $request): JsonResponse
    {
        $ids = $this->tenantIds($request);
        $clarifications = AiClarification::query()
            ->whereIn('tenant_id', $ids)
            ->where('status', 'open')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AiClarification $row): array => [
                'id' => $row->id,
                'kind' => 'clarification',
                'conversation_id' => $row->conversation_id,
                'operation_request_id' => $row->operation_request_id,
                'title' => 'Pending clarification',
                'detail' => $row->question,
                'status' => $row->status,
            ])->values();
        $approvals = AiApproval::query()
            ->with('operation')
            ->whereIn('tenant_id', $ids)
            ->where('status', 'pending')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AiApproval $row): array => [
                'id' => $row->id,
                'kind' => 'approval',
                'conversation_id' => $row->operation?->conversation_id,
                'operation_request_id' => $row->operation_request_id,
                'title' => 'Pending approval',
                'detail' => (string) ($row->approval_snapshot['operation'] ?? 'Operation waiting for approval'),
                'status' => $row->status,
            ])->values();
        $failed = AiOperationRequest::query()
            ->whereIn('tenant_id', $ids)
            ->whereIn('status', ['failed', 'unknown'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AiOperationRequest $row): array => [
                'id' => $row->id,
                'kind' => $row->status === 'unknown' ? 'unknown' : 'failed',
                'conversation_id' => $row->conversation_id,
                'operation_request_id' => $row->id,
                'title' => $row->status === 'unknown' ? 'Unknown outcome' : 'Failed operation',
                'detail' => $row->intent ?: 'Operation',
                'status' => $row->status,
            ])->values();
        $escalations = AiEscalation::query()
            ->whereIn('tenant_id', $ids)
            ->where('status', 'open')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AiEscalation $row): array => [
                'id' => $row->id,
                'kind' => 'escalation',
                'conversation_id' => $row->conversation_id,
                'operation_request_id' => $row->operation_request_id,
                'title' => 'Human escalation',
                'detail' => $row->reason,
                'status' => $row->status,
            ])->values();

        return $this->ok('Flags loaded.', [
            'flags' => $clarifications->concat($approvals)->concat($failed)->concat($escalations)->values(),
        ]);
    }

    public function rules(Request $request): JsonResponse
    {
        $tenant = $this->selectedTenant($request);
        $admin = $this->admin($request);
        $this->access->assert($admin, $tenant, 'tenants.view');
        $rules = $this->logic->list($admin, $tenant->id, 'rule')
            ->through(fn (AiLogicItem $rule): array => $this->payload->rule($rule));

        return $this->ok('Rules loaded.', [
            'can_manage' => $this->access->allows($admin, $tenant, 'ai.memory.manage'),
            'rules' => $rules,
        ]);
    }

    public function chatRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'rule_id' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'max:4000'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);
        $tenant = $this->tenant($request, $data['tenant_id']);
        $this->access->assert($this->admin($request), $tenant, 'ai.memory.manage');

        $title = $data['title'] ?? null;
        $body = $data['body'] ?? null;
        if (! empty($data['rule_id'])) {
            $rule = AiLogicItem::query()
                ->where('tenant_id', $tenant->id)
                ->where('kind', 'rule')
                ->findOrFail($data['rule_id']);
            $title = ($title === null || $title === '') ? $rule->title : $title;
            $body = ($body === null || $body === '') ? $rule->body : $body;
        }

        $draft = $this->rules->draft($data['message'], is_string($title) ? $title : null, is_string($body) ? $body : null);

        return $this->ok($draft['reply'], $draft);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $rule = $this->logic->store($this->admin($request), $data);

        return $this->ok('Rule saved.', ['rule' => $this->payload->rule($rule)], 201);
    }

    public function updateRule(Request $request, AiLogicItem $rule): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $rule = $this->logic->update($this->admin($request), $rule, $data);

        return $this->ok('Rule updated.', ['rule' => $this->payload->rule($rule)]);
    }

    public function documents(Request $request): JsonResponse
    {
        $tenant = $this->selectedTenant($request);
        $admin = $this->admin($request);
        $this->access->assert($admin, $tenant, 'tenants.view');
        $documents = AiKnowledgeDocument::query()
            ->with('tenant')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(50)
            ->through(fn (AiKnowledgeDocument $document): array => $this->payload->document($document, false));

        return $this->ok('Documents loaded.', [
            'can_manage' => $this->access->allows($admin, $tenant, 'ai.memory.manage'),
            'documents' => $documents,
        ]);
    }

    public function showDocument(Request $request, AiKnowledgeDocument $document): JsonResponse
    {
        $this->guardDocument($request, $document, 'tenants.view');

        return $this->ok('Document loaded.', ['document' => $this->payload->document($document, true)]);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:50000'],
        ]);
        $file = $this->knowledgeFile($request, true);
        $tenant = $this->tenant($request, $data['tenant_id']);
        $this->access->assert($this->admin($request), $tenant, 'ai.memory.manage');
        $document = $this->knowledge->store($tenant, $data['title'], $data['content'] ?? null, $file);

        return $this->ok('Document stored. It stays out of retrieval until it is approved.', [
            'document' => $this->payload->document($document, false),
        ], 201);
    }

    public function updateDocument(Request $request, AiKnowledgeDocument $document): JsonResponse
    {
        $this->guardDocument($request, $document, 'ai.memory.manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:50000'],
        ]);
        $file = $this->knowledgeFile($request, false);
        $document = $this->knowledge->update($document, $data['title'], $data['content'] ?? null, $file);

        return $this->ok('Document updated. It waits for approval again before retrieval.', [
            'document' => $this->payload->document($document, true),
        ]);
    }

    public function approveDocument(Request $request, AiKnowledgeDocument $document): JsonResponse
    {
        $this->guardDocument($request, $document, 'ai.memory.manage');
        if ($document->approval_status !== 'approved') {
            $document->approval_status = 'approved';
            $document->save();
        }

        return $this->ok('Document approved.', ['document' => $this->payload->document($document, false)]);
    }

    public function deleteDocument(Request $request, AiKnowledgeDocument $document): JsonResponse
    {
        $this->guardDocument($request, $document, 'ai.memory.manage');
        $this->knowledge->delete($document);

        return $this->ok('Document deleted.', []);
    }

    public function access(Request $request): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid']]);
        $tenant = $this->tenant($request, $data['tenant_id']);
        $this->access->assert($this->admin($request), $tenant, 'tenants.view');

        return $this->ok('Access loaded.', $this->payload->access($this->admin($request), $tenant));
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'operation_request_id' => ['nullable', 'uuid'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'correction' => ['nullable', 'string', 'max:5000'],
        ]);
        $tenant = $this->tenant($request, $data['tenant_id']);
        $this->access->assert($this->admin($request), $tenant, 'ai.chat.use');
        $feedback = new \App\Models\AiFeedback();
        $feedback->fill($data);
        $feedback->submitted_by = $this->admin($request)->id;
        $feedback->review_status = 'pending';
        $feedback->save();

        return $this->ok('Feedback stored.', ['feedback_id' => $feedback->id], 201);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        if (! $admin instanceof Admin) {
            abort(401, 'Your session has expired. Sign in again.');
        }

        return $admin;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function tenantIds(Request $request): \Illuminate\Support\Collection
    {
        $allowed = $this->access->tenantsFor($this->admin($request))->pluck('id');
        $selected = $request->query('tenant_id');
        if (! is_string($selected) || $selected === '' || $selected === 'all') {
            return $allowed->values();
        }
        abort_unless($allowed->contains($selected), 403, 'You do not have access to this business unit.');

        return collect([$selected]);
    }

    private function knowledgeFile(Request $request, bool $creating): ?UploadedFile
    {
        $file = $request->file('file');
        if ($creating && ! $request->filled('content') && ! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'content' => 'Add the text or upload a file.',
            ]);
        }
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $allowed = [
            'txt' => ['text/plain'],
            'md' => ['text/plain', 'text/markdown'],
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
            'json' => ['application/json', 'text/plain', 'text/json'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
            throw ValidationException::withMessages([
                'file' => 'Upload a TXT, MD, CSV, JSON, DOC, DOCX, or PDF file.',
            ]);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'The file must be 10 MB or smaller.',
            ]);
        }

        return $file;
    }

    private function selectedTenant(Request $request): Tenant
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid']]);

        return $this->tenant($request, $data['tenant_id']);
    }

    private function guardDocument(Request $request, AiKnowledgeDocument $document, string $permission): void
    {
        $document->loadMissing('tenant');
        $this->access->assert($this->admin($request), $document->tenant, $permission);
    }

    private function tenant(Request $request, string $id): Tenant
    {
        $tenant = $this->access->tenantsFor($this->admin($request))->firstWhere('id', $id);
        if (! $tenant instanceof Tenant) {
            abort(403, 'You do not have access to this business unit.');
        }

        return $tenant;
    }

    private function guardConversation(Request $request, AiConversation $conversation, string $permission = 'tenants.view'): void
    {
        $this->access->assert($this->admin($request), $conversation->tenant, $permission);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ok(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }
}
