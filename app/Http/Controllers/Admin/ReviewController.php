<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiApproval;
use App\Models\AiEscalation;
use App\Models\AiOperationRequest;
use App\Services\AccessService;
use App\Services\Operations\InstructionPipeline;
use App\Services\Operations\OperationStateMachine;
use App\Exceptions\IllegalTransitionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReviewController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly InstructionPipeline $pipeline,
        private readonly OperationStateMachine $states,
    ) {}

    public function operations(Request $request): View
    {
        $ids = $this->access->scopedTenantIds($request->user('admin'), $request->session()->get('selected_tenant_id'));
        $status = (string) $request->query('status', '');
        $operations = AiOperationRequest::query()
            ->with('tenant')
            ->whereIn('tenant_id', $ids)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(150)
            ->withQueryString();

        return view('admin.operations.index', compact('operations', 'status'));
    }

    public function showOperation(Request $request, AiOperationRequest $operation): View
    {
        $this->access->assert($request->user('admin'), $operation->tenant, 'tenants.view');
        $operation->load(['steps', 'executions', 'history', 'approvals', 'clarifications', 'escalations', 'tenant']);

        return view('admin.operations.show', compact('operation'));
    }

    public function reconcile(Request $request, AiOperationRequest $operation): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $operation->tenant, 'ai.operations.retry');
        $this->pipeline->reconcile($operation, $request->user('admin'));

        return back()->with('success', 'Reconciliation finished.');
    }

    public function cancel(Request $request, AiOperationRequest $operation): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $operation->tenant, 'ai.chat.use');
        try {
            $this->states->transition($operation, 'cancelled', 'Cancelled by '.$request->user('admin')->username.'.', $request->user('admin'));
        } catch (IllegalTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Operation cancelled.');
    }

    public function approvals(Request $request): View
    {
        $ids = $this->access->scopedTenantIds($request->user('admin'), $request->session()->get('selected_tenant_id'));
        $approvals = AiApproval::query()->with('operation.tenant')->whereIn('tenant_id', $ids)->latest()->paginate(150);

        return view('admin.reviews.approvals', ['approvals' => $approvals]);
    }

    public function approve(Request $request, AiApproval $approval): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        try {
            $this->pipeline->approve($approval, $request->user('admin'), $data['reason'] ?? null);
        } catch (HttpException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Approved. The connector has run the operation.');
    }

    public function reject(Request $request, AiApproval $approval): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            $this->pipeline->reject($approval, $request->user('admin'), $data['reason']);
        } catch (HttpException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Approval rejected.');
    }

    public function escalations(Request $request): View
    {
        $ids = $this->access->scopedTenantIds($request->user('admin'), $request->session()->get('selected_tenant_id'));
        $escalations = AiEscalation::query()->with('tenant')->whereIn('tenant_id', $ids)->latest()->paginate(150);

        return view('admin.reviews.escalations', compact('escalations'));
    }

    public function resolveEscalation(Request $request, AiEscalation $escalation): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $escalation->tenant, 'ai.escalations.resolve');
        $data = $request->validate(['resolution' => ['required', 'string', 'max:2000']]);
        $escalation->status = 'resolved';
        $escalation->resolution = $data['resolution'];
        $escalation->resolved_by = $request->user('admin')->id;
        $escalation->save();

        return back()->with('success', 'Escalation resolved.');
    }
}
