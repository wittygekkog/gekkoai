<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogicChatRequest;
use App\Http\Requests\Admin\LogicItemRequest;
use App\Models\AiLogicItem;
use App\Services\AccessService;
use App\Services\LogicService;
use App\Services\RuleChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Logic lists standing rules. Add and edit happen in the same chat offcanvas.
 */
class LogicController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly LogicService $logic,
        private readonly RuleChatService $chat,
    ) {}

    public function index(Request $request): View
    {
        $admin = $request->user('admin');
        $selected = $request->session()->get('selected_tenant_id');
        $tenants = $this->access->tenantsFor($admin);
        if ($this->access->isSpecificTenant($selected)) {
            $tenants = $tenants->where('id', $selected)->values();
        }

        return view('admin.logic.index', [
            'rules' => $this->logic->list($admin, $selected, 'rule'),
            'tenants' => $tenants,
            'canManage' => $tenants->contains(fn ($tenant): bool => $this->access->allows($admin, $tenant, 'ai.memory.manage')),
        ]);
    }

    public function chat(LogicChatRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenant = $this->access->tenantsFor($request->user('admin'))->firstWhere('id', $data['tenant_id']);
        abort_if($tenant === null, 403);
        $this->access->assert($request->user('admin'), $tenant, 'ai.memory.manage');

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

        $draft = $this->chat->draft($data['message'], $title, $body);

        return response()->json([
            'success' => true,
            'message' => $draft['reply'],
            'data' => $draft,
        ]);
    }

    public function store(LogicItemRequest $request): RedirectResponse
    {
        $this->logic->store($request->user('admin'), $request->validated());

        return back()->with('success', 'Rule saved. It does not grant a new operation.');
    }

    public function update(LogicItemRequest $request, AiLogicItem $rule): RedirectResponse
    {
        $this->logic->update($request->user('admin'), $rule, $request->safe()->only(['title', 'body']));

        return back()->with('success', 'Rule updated.');
    }
}
