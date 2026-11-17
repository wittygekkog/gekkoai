<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MessageRequest;
use App\Models\AiApproval;
use App\Models\AiConversation;
use App\Models\AiEscalation;
use App\Models\AiFeedback;
use App\Models\AiOperationRequest;
use App\Models\Tenant;
use App\Services\AccessService;
use App\Services\Operations\InstructionPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformApiController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly InstructionPipeline $pipeline,
    ) {}

    public function tenants(Request $request): JsonResponse
    {
        return $this->ok('Tenants loaded.', [
            'tenants' => $this->access->tenantsFor($request->user('admin'))->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
            ])->values(),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $ids = $this->access->tenantsFor($request->user('admin'))->pluck('id');
        $rows = AiConversation::query()->whereIn('tenant_id', $ids)->latest('updated_at')->paginate(50);

        return $this->ok('Conversations loaded.', ['conversations' => $rows]);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid']]);
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $this->access->assert($request->user('admin'), $tenant, 'ai.chat.use');
        $conversation = new AiConversation();
        $conversation->tenant_id = $tenant->id;
        $conversation->user_id = $request->user('admin')->id;
        $conversation->title = 'New conversation';
        $conversation->status = 'open';
        $conversation->model_provider = 'deterministic';
        $conversation->model_name = 'Gekko baseline parser';
        $conversation->save();

        return $this->ok('Conversation created.', ['conversation' => $conversation], 201);
    }

    public function message(MessageRequest $request, AiConversation $conversation): JsonResponse
    {
        $this->access->assert($request->user('admin'), $conversation->tenant, 'ai.chat.use');
        $operation = $this->pipeline->handle(
            $conversation,
            $request->user('admin'),
            trim($request->string('body')->toString()),
            array_filter($request->input('fields', [])),
        );

        return $this->ok('Instruction processed.', [
            'operation' => $operation->only(['id', 'status', 'intent', 'parameters', 'missing_fields', 'confidence_score', 'risk_level']),
        ]);
    }

    public function operations(Request $request): JsonResponse
    {
        $ids = $this->access->tenantsFor($request->user('admin'))->pluck('id');
        $rows = AiOperationRequest::query()->whereIn('tenant_id', $ids)->latest()->paginate(50);

        return $this->ok('Operations loaded.', ['operations' => $rows]);
    }

    public function showOperation(Request $request, AiOperationRequest $operation): JsonResponse
    {
        $this->access->assert($request->user('admin'), $operation->tenant, 'tenants.view');

        return $this->ok('Operation loaded.', ['operation' => $operation->load(['steps', 'executions', 'history'])]);
    }

    public function approve(Request $request, AiApproval $approval): JsonResponse
    {
        $operation = $this->pipeline->approve($approval, $request->user('admin'), $request->string('reason')->toString() ?: null);

        return $this->ok('Approval accepted.', ['operation' => $operation->only(['id', 'status', 'intent'])]);
    }

    public function reject(Request $request, AiApproval $approval): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->pipeline->reject($approval, $request->user('admin'), $data['reason']);

        return $this->ok('Approval rejected.', []);
    }

    public function escalations(Request $request): JsonResponse
    {
        $ids = $this->access->tenantsFor($request->user('admin'))->pluck('id');

        return $this->ok('Escalations loaded.', [
            'escalations' => AiEscalation::query()->whereIn('tenant_id', $ids)->latest()->paginate(50),
        ]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'operation_request_id' => ['nullable', 'uuid'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'correction' => ['nullable', 'string', 'max:5000'],
        ]);
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $this->access->assert($request->user('admin'), $tenant, 'ai.chat.use');
        $feedback = new AiFeedback();
        $feedback->fill($data);
        $feedback->submitted_by = $request->user('admin')->id;
        $feedback->review_status = 'pending';
        $feedback->save();

        return $this->ok('Feedback stored.', ['feedback_id' => $feedback->id], 201);
    }

    private function ok(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }
}
