<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantConnector;
use App\Services\AccessService;
use App\Services\Connectors\OperationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private readonly AccessService $access) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $tenants = $this->access->tenantsFor($request->user('admin'));
        $items = Tenant::query()
            ->with('connector')
            ->whereIn('id', $tenants->pluck('id'))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->paginate(150)
            ->withQueryString();

        return view('admin.tenants.index', compact('items', 'q'));
    }

    public function create(): View
    {
        return view('admin.tenants.form', ['tenant' => new Tenant(['timezone' => 'Asia/Manila', 'locale' => 'en', 'status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuperAdmin() || $request->user('admin')->access_scope === 'platform', 403);
        $data = $this->validated($request);
        $tenant = new Tenant();
        $tenant->fill($data);
        $tenant->slug = Str::slug($data['slug'] ?: $data['name']);
        $this->storeLogo($request, $tenant);
        $tenant->save();
        $connector = new TenantConnector();
        $connector->tenant_id = $tenant->id;
        $connector->mode = 'simulation';
        $connector->status = 'simulation';
        $connector->base_url = 'simulation://'.$tenant->slug;
        $connector->save();

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Business unit created. Connector is in simulation mode.');
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $this->access->assert($request->user('admin'), $tenant, 'tenants.view');
        $tenant->load(['connector', 'operations', 'memberships.admin']);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'catalog' => OperationCatalog::definitions(),
        ]);
    }

    public function edit(Request $request, Tenant $tenant): View
    {
        $this->access->assert($request->user('admin'), $tenant, 'tenants.manage');

        return view('admin.tenants.form', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $tenant, 'tenants.manage');
        $tenant->fill($this->validated($request));
        $this->storeLogo($request, $tenant);
        $tenant->save();

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Business unit updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'string', 'max:12'],
            'status' => ['required', 'in:active,inactive'],
            'logo' => ['nullable', 'image', 'mimes:webp,jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function storeLogo(Request $request, Tenant $tenant): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }
        $tenant->logo_path = $request->file('logo')->store('tenants', 'public');
    }
}
