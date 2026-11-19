<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ObjectiveChatRequest;
use App\Http\Requests\Admin\ObjectiveItemRequest;
use App\Models\AiLogicItem;
use App\Services\AccessService;
use App\Services\ObjectiveChatService;
use App\Services\ObjectiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Objective lists the outcome for the selected SBU.
 * Add and edit use the same chat, which tightens the wording and cannot override Logic.
 */
class ObjectiveController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ObjectiveService $objectives,
        private readonly ObjectiveChatService $chat,
    ) {}

    public function index(Request $request): View
    {
        $admin = $request->user('admin');
        $selected = $request->session()->get('selected_tenant_id');
        $tenants = $this->access->tenantsFor($admin);
        if ($this->access->isSpecificTenant($selected)) {
            $tenants = $tenants->where('id', $selected)->values();
        }

        return view('admin.objective.index', [
            'objectives' => $this->objectives->list($admin, $selected),
            'tenants' => $tenants,
            'canManage' => $tenants->contains(fn ($tenant): bool => $this->access->allows($admin, $tenant, 'ai.memory.manage')),
        ]);
    }

    public function chat(ObjectiveChatRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenant = $this->access->tenantsFor($request->user('admin'))->firstWhere('id', $data['tenant_id']);
        abort_if($tenant === null, 403);
        $this->access->assert($request->user('admin'), $tenant, 'ai.memory.manage');

        $title = $data['title'] ?? null;
        $body = $data['body'] ?? null;
        if (! empty($data['objective_id'])) {
            $objective = AiLogicItem::query()
                ->where('tenant_id', $tenant->id)
                ->where('kind', 'objective')
                ->findOrFail($data['objective_id']);
            $title = ($title === null || $title === '') ? $objective->title : $title;
            $body = ($body === null || $body === '') ? $objective->body : $body;
        }

        $draft = $this->chat->draft($data['message'], $title, $body);

        return response()->json([
            'success' => true,
            'message' => $draft['reply'],
            'data' => $draft,
        ]);
    }

    public function store(ObjectiveItemRequest $request): RedirectResponse
    {
        $this->objectives->store($request->user('admin'), $request->validated());

        return back()->with('success', 'Objective saved. It does not override Rules or grant a tool.');
    }

    public function update(ObjectiveItemRequest $request, AiLogicItem $objective): RedirectResponse
    {
        $this->objectives->update($request->user('admin'), $objective, $request->safe()->only(['title', 'body']));

        return back()->with('success', 'Objective updated.');
    }
}
