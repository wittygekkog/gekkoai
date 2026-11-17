<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MessageRequest;
use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiTranscription;
use App\Models\Tenant;
use App\Services\AccessService;
use App\Services\Operations\InstructionPipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly InstructionPipeline $pipeline,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $tenant = $this->selectedTenant($request);
        if ($tenant === null) {
            return view('admin.workspace.empty', ['tenants' => $this->access->tenantsFor($request->user('admin'))]);
        }
        $conversation = AiConversation::query()
            ->where('tenant_id', $tenant->id)
            ->latest('updated_at')
            ->first();
        if ($conversation === null) {
            $conversation = $this->makeConversation($request, $tenant);
        }

        return redirect()->route('admin.workspace.show', $conversation);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid']]);
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $this->access->assert($request->user('admin'), $tenant, 'ai.chat.use');
        $request->session()->put('selected_tenant_id', $tenant->id);

        return redirect()->route('admin.workspace.show', $this->makeConversation($request, $tenant));
    }

    public function show(Request $request, AiConversation $conversation): View
    {
        $this->guard($request, $conversation);
        $request->session()->put('selected_tenant_id', $conversation->tenant_id);
        $conversation->load(['messages', 'tenant.connector', 'tenant.operations', 'operations.approvals', 'operations.steps']);
        $tenants = $this->access->tenantsFor($request->user('admin'));
        $conversations = AiConversation::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->latest('updated_at')
            ->limit(30)
            ->get();
        $operation = $conversation->operations->first();

        return view('admin.workspace.show', compact('conversation', 'tenants', 'conversations', 'operation'));
    }

    public function message(MessageRequest $request, AiConversation $conversation): RedirectResponse
    {
        $this->guard($request, $conversation, 'ai.chat.use');
        $body = trim($request->string('body')->toString());
        $fields = array_filter($request->input('fields', []), static fn ($value): bool => $value !== null && $value !== '');
        if ($body === '' && $fields === []) {
            return back()->with('error', 'Enter an instruction.');
        }
        $this->pipeline->handle($conversation, $request->user('admin'), $body, $fields);

        return redirect()->route('admin.workspace.show', $conversation);
    }

    public function audio(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->guard($request, $conversation, 'ai.chat.use');
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:'.config('gekko.audio_max_kilobytes'), 'mimetypes:audio/webm,audio/wav,audio/x-wav,audio/mpeg,audio/ogg,audio/mp4,video/webm'],
        ]);
        $file = $data['audio'];
        $path = $file->store('audio/'.$conversation->tenant_id);
        $attachment = new AiAttachment();
        $attachment->tenant_id = $conversation->tenant_id;
        $attachment->file_path = $path;
        $attachment->mime_type = (string) $file->getMimeType();
        $attachment->file_size = (int) $file->getSize();
        $attachment->retention_until = now()->addDays((int) config('gekko.audio_retention_days'));
        $attachment->save();

        $transcription = new AiTranscription();
        $transcription->tenant_id = $conversation->tenant_id;
        $transcription->attachment_id = $attachment->id;
        $transcription->model_name = 'unconfigured';
        $transcription->transcription_metadata = [
            'status' => 'provider_unconfigured',
            'detail' => 'No speech model is configured. Type the confirmed transcript to continue.',
        ];
        $transcription->save();

        return redirect()->route('admin.workspace.show', $conversation)
            ->with('transcript_id', $transcription->id)
            ->with('success', 'Recording stored. No transcription model is configured, so confirm the words below before they are sent.');
    }

    public function confirmTranscript(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->guard($request, $conversation, 'ai.chat.use');
        $data = $request->validate([
            'transcription_id' => ['required', 'uuid'],
            'corrected_transcript' => ['required', 'string', 'min:2', 'max:4000'],
        ]);
        $transcription = AiTranscription::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->findOrFail($data['transcription_id']);
        $transcription->corrected_transcript = $data['corrected_transcript'];
        $transcription->save();
        $this->pipeline->handle($conversation, $request->user('admin'), $data['corrected_transcript']);

        return redirect()->route('admin.workspace.show', $conversation);
    }

    public function conversations(Request $request): View
    {
        $ids = $this->access->scopedTenantIds($request->user('admin'), $request->session()->get('selected_tenant_id'));
        $status = (string) $request->query('status', '');
        $conversations = AiConversation::query()
            ->with('tenant')
            ->whereIn('tenant_id', $ids)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(150)
            ->withQueryString();

        return view('admin.conversations.index', compact('conversations', 'status'));
    }

    private function selectedTenant(Request $request): ?Tenant
    {
        $tenants = $this->access->tenantsFor($request->user('admin'));
        $selected = $request->session()->get('selected_tenant_id');
        if (is_string($selected) && $selected !== 'all') {
            $tenant = $tenants->firstWhere('id', $selected);
            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        return $tenants->firstWhere('slug', 'the-inner-cove') ?? $tenants->first();
    }

    private function makeConversation(Request $request, Tenant $tenant): AiConversation
    {
        $this->access->assert($request->user('admin'), $tenant, 'ai.chat.use');
        $conversation = new AiConversation();
        $conversation->tenant_id = $tenant->id;
        $conversation->user_id = $request->user('admin')->id;
        $conversation->title = 'New conversation';
        $conversation->status = 'open';
        $conversation->model_provider = config('gekko.analyzer') === 'http' ? 'http' : 'deterministic';
        $conversation->model_name = config('gekko.analyzer') === 'http' ? 'configured-provider' : 'Gekko baseline parser';
        $conversation->save();

        return $conversation;
    }

    private function guard(Request $request, AiConversation $conversation, string $permission = 'tenants.view'): void
    {
        $this->access->assert($request->user('admin'), $conversation->tenant, $permission);
    }
}
