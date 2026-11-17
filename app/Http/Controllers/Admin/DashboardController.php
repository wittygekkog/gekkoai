<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiEscalation;
use App\Models\AiOperationRequest;
use App\Models\Tenant;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AccessService $access) {}

    public function index(Request $request): View
    {
        $admin = $request->user('admin');
        $tenants = $this->access->tenantsFor($admin);
        $tenantId = $request->session()->get('selected_tenant_id');
        $ids = $this->access->scopedTenantIds($admin, $tenantId);

        $base = AiOperationRequest::query()->whereIn('tenant_id', $ids);
        $today = (clone $base)->whereDate('created_at', today());
        $weekStart = now()->subDays(6)->startOfDay();
        $volume = (clone $base)->where('created_at', '>=', $weekStart)
            ->get(['created_at'])
            ->groupBy(fn (AiOperationRequest $row): string => $row->created_at->format('D'));

        $days = collect(range(0, 6))->map(function (int $offset) use ($volume): array {
            $day = now()->subDays(6 - $offset);
            $label = $day->format('D');

            return ['label' => $label, 'count' => $volume->get($label, collect())->count()];
        });

        $counts = [
            'today' => (clone $today)->count(),
            'automated' => (clone $base)->where('execution_mode', 'automatic')->where('status', 'completed')->whereDate('created_at', today())->count(),
            'approvals' => (clone $base)->where('status', 'awaiting_approval')->count(),
            'escalations' => AiEscalation::query()->whereIn('tenant_id', $ids)->where('status', 'open')->count(),
            'successful' => (clone $base)->where('status', 'completed')->count(),
            'review' => (clone $base)->whereIn('status', ['awaiting_approval', 'awaiting_clarification', 'escalated'])->count(),
            'failed' => (clone $base)->whereIn('status', ['failed', 'unknown_outcome'])->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $recent = (clone $base)->with('tenant')->latest()->limit(8)->get();
        $connectors = Tenant::query()->with('connector')->whereIn('id', $tenants->pluck('id'))->orderBy('name')->get();

        return view('admin.dashboard', compact('tenants', 'tenantId', 'counts', 'days', 'recent', 'connectors'));
    }

    public function switchTenant(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'string']]);
        if (in_array($data['tenant_id'], ['general', 'all'], true)) {
            $request->session()->put('selected_tenant_id', 'general');

            return back();
        }
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        abort_unless($this->access->allows($request->user('admin'), $tenant, 'tenants.view'), 403);
        $request->session()->put('selected_tenant_id', $tenant->id);

        return back();
    }
}
