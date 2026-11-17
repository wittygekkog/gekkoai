<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAuditLog;
use App\Models\AiEvaluationRun;
use App\Models\AiModelVersion;
use App\Models\TenantConnector;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function __construct(private readonly AccessService $access) {}

    public function connectors(Request $request): View
    {
        $ids = $this->access->scopedTenantIds($request->user('admin'), $request->session()->get('selected_tenant_id'));
        $connectors = TenantConnector::query()->with('tenant')->whereIn('tenant_id', $ids)->orderBy('status')->get();

        return view('admin.system.connectors', compact('connectors'));
    }

    public function health(Request $request, TenantConnector $connector): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $connector->tenant, 'ai.connectors.manage');
        $connector->last_health_check_at = now();
        if ($connector->mode === 'simulation') {
            $connector->status = 'simulation';
            $connector->last_health_message = 'Simulation connector is available. No live Inner Cove call was made.';
        } else {
            $connector->status = 'not_configured';
            $connector->last_health_message = 'Live calls are disabled until GEKKO_ALLOW_LIVE_CONNECTORS is turned on and an HTTPS base URL is saved.';
        }
        $connector->save();

        return back()->with('success', $connector->last_health_message);
    }

    public function models(): View
    {
        return view('admin.system.models', [
            'models' => AiModelVersion::query()->orderBy('purpose')->get(),
        ]);
    }

    public function storeModel(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuperAdmin(), 403);
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:80'],
            'model_name' => ['required', 'string', 'max:120'],
            'purpose' => ['required', 'in:intent,extraction,conversation,retrieval,planning,transcription'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);
        $model = new AiModelVersion();
        $model->provider = $data['provider'];
        $model->model_name = $data['model_name'];
        $model->purpose = $data['purpose'];
        $model->version = 'configured';
        $model->deployment_status = 'inactive';
        $model->configuration = [
            'base_url' => $data['base_url'] ?? null,
            'api_key' => $data['api_key'] ? Crypt::encryptString($data['api_key']) : null,
        ];
        $model->created_at = now();
        $model->save();

        return back()->with('success', 'Model configuration stored. It is inactive until an evaluation passes.');
    }

    public function confidence(): View
    {
        return view('admin.system.confidence', [
            'runs' => AiEvaluationRun::query()->latest('evaluated_at')->limit(20)->get(),
        ]);
    }

    public function audit(Request $request): View
    {
        $selected = $request->session()->get('selected_tenant_id');
        $ids = $this->access->scopedTenantIds($request->user('admin'), $selected);
        $logs = AiAuditLog::query()
            ->where(function ($query) use ($ids, $selected): void {
                $query->whereIn('tenant_id', $ids);
                if (! $this->access->isSpecificTenant($selected)) {
                    $query->orWhereNull('tenant_id');
                }
            })
            ->latest('created_at')
            ->paginate(150);

        return view('admin.system.audit', compact('logs'));
    }
}
