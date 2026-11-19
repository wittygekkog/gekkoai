{{-- Sidebar: logo, scope switch, then either platform links or the selected SBU's links. --}}
@php
    $navTenants = ($admin->isSuperAdmin() || $admin->access_scope === 'platform')
        ? \App\Models\Tenant::query()->where('status', 'active')->orderBy('name')->get()
        : $admin->tenants()->where('tenants.status', 'active')->wherePivot('status', 'active')->orderBy('name')->get();
    $rawScope = session('selected_tenant_id');
    $selectedTenant = (is_string($rawScope) && ! in_array($rawScope, ['all', 'general'], true))
        ? $navTenants->firstWhere('id', $rawScope)
        : null;
    $canManageAdmins = $admin->isSuperAdmin() || ($admin->admin_type === 1 && $admin->access_scope === 'platform');
    $canManageTenant = $selectedTenant && app(\App\Services\AccessService::class)->allows($admin, $selectedTenant, 'tenants.manage');
    $linkActive = function (string ...$patterns): string {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return 'active';
            }
        }

        return '';
    };
@endphp
<aside class="gk-sidebar" id="sidebar" data-scope="{{ $selectedTenant ? 'sbu' : 'general' }}">
    <div class="gk-sidebar-head">
        <a class="gk-brand" href="{{ route('admin.dashboard') }}">
            <img src="{{ asset('images/gekkoai-logo.png') }}" alt="GekkoAi">
        </a>
        <button class="btn gk-icon-btn gk-sidebar-close d-lg-none" type="button" data-menu aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>

    <form method="post" action="{{ route('admin.tenant.switch') }}" class="gk-switch search-form">
        @csrf
        <label class="gk-switch-label" for="navScopeSelect">Workspace</label>
        <select id="navScopeSelect" name="tenant_id" class="form-select" aria-label="Choose general administration or an SBU">
            <option value="general" @selected(! $selectedTenant)>General Administration</option>
            @foreach ($navTenants as $navTenant)
                <option value="{{ $navTenant->id }}" @selected($selectedTenant?->id === $navTenant->id)>{{ $navTenant->name }}</option>
            @endforeach
        </select>
    </form>

    <nav class="gk-nav" aria-label="Primary">
        @if ($selectedTenant)
            <div class="gk-nav-section text-truncate" title="{{ $selectedTenant->name }}">{{ $selectedTenant->name }}</div>
            <a href="{{ route('admin.tenants.show', $selectedTenant) }}" class="{{ $linkActive('admin.tenants.show') }}" @if(request()->routeIs('admin.tenants.show')) aria-current="page" @endif>
                <i class="bi bi-building"></i> Overview
            </a>
            @if ($canManageTenant)
                <a href="{{ route('admin.tenants.edit', $selectedTenant) }}" class="{{ $linkActive('admin.tenants.edit') }}" @if(request()->routeIs('admin.tenants.edit')) aria-current="page" @endif>
                    <i class="bi bi-sliders"></i> Profile
                </a>
            @endif
            <a href="{{ route('admin.workspace.index') }}" class="{{ $linkActive('admin.workspace.*') }}" @if(request()->routeIs('admin.workspace.*')) aria-current="page" @endif>
                <i class="bi bi-stars"></i> AI Workspace
            </a>
            <a href="{{ route('admin.objective.index') }}" class="{{ $linkActive('admin.objective.*') }}" @if(request()->routeIs('admin.objective.*')) aria-current="page" @endif>
                <i class="bi bi-bullseye"></i> Objective
            </a>
            <a href="{{ route('admin.logic.index') }}" class="{{ $linkActive('admin.logic.*') }}" @if(request()->routeIs('admin.logic.*')) aria-current="page" @endif>
                <i class="bi bi-diagram-3"></i> Rules
            </a>
            <a href="{{ route('admin.knowledge.index') }}" class="{{ $linkActive('admin.knowledge.*') }}" @if(request()->routeIs('admin.knowledge.*')) aria-current="page" @endif>
                <i class="bi bi-journal-text"></i> Data
            </a>
            <a href="{{ route('admin.operations.index') }}" class="{{ $linkActive('admin.operations.*') }}" @if(request()->routeIs('admin.operations.*')) aria-current="page" @endif>
                <i class="bi bi-clock-history"></i> Logs
            </a>
            <a href="{{ route('admin.approvals.index') }}" class="{{ $linkActive('admin.approvals.*') }}" @if(request()->routeIs('admin.approvals.*')) aria-current="page" @endif>
                <i class="bi bi-check2-square"></i> Pending Approvals
            </a>
            <a href="{{ route('admin.escalations.index') }}" class="{{ $linkActive('admin.escalations.*') }}" @if(request()->routeIs('admin.escalations.*')) aria-current="page" @endif>
                <i class="bi bi-exclamation-triangle"></i> Flags
            </a>
            <form method="post" action="{{ route('admin.tenant.switch') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="general">
                <button class="gk-back" type="submit"><i class="bi bi-arrow-left"></i> General administration</button>
            </form>
        @else
            <a href="{{ route('admin.dashboard') }}" class="{{ $linkActive('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <div class="gk-nav-section">Business units</div>
            <a href="{{ route('admin.tenants.index') }}" class="{{ $linkActive('admin.tenants.*') }}" @if(request()->routeIs('admin.tenants.*')) aria-current="page" @endif>
                <i class="bi bi-buildings"></i> SBUs
            </a>
            <a href="{{ route('admin.connectors.index') }}" class="{{ $linkActive('admin.connectors.*') }}" @if(request()->routeIs('admin.connectors.*')) aria-current="page" @endif>
                <i class="bi bi-plug"></i> SBU Connectors
            </a>
            <div class="gk-nav-section">Intelligence</div>
            <a href="{{ route('admin.models.index') }}" class="{{ $linkActive('admin.models.*') }}" @if(request()->routeIs('admin.models.*')) aria-current="page" @endif>
                <i class="bi bi-cpu"></i> AI Models
            </a>
            <a href="{{ route('admin.knowledge.index') }}" class="{{ $linkActive('admin.knowledge.*') }}" @if(request()->routeIs('admin.knowledge.*')) aria-current="page" @endif>
                <i class="bi bi-journal-text"></i> Data
            </a>
            <a href="{{ route('admin.learning.index') }}" class="{{ $linkActive('admin.learning.*') }}" @if(request()->routeIs('admin.learning.*')) aria-current="page" @endif>
                <i class="bi bi-mortarboard"></i> Learning Center
            </a>
            <a href="{{ route('admin.confidence.index') }}" class="{{ $linkActive('admin.confidence.*') }}" @if(request()->routeIs('admin.confidence.*')) aria-current="page" @endif>
                <i class="bi bi-graph-up"></i> Confidence Analytics
            </a>
            <div class="gk-nav-section">Platform</div>
            <a href="{{ route('admin.audit.index') }}" class="{{ $linkActive('admin.audit.*') }}" @if(request()->routeIs('admin.audit.*')) aria-current="page" @endif>
                <i class="bi bi-shield-check"></i> Audit Logs
            </a>
            @if ($canManageAdmins)
                <a href="{{ route('admin.access.index') }}" class="{{ $linkActive('admin.access.*') }}" @if(request()->routeIs('admin.access.*')) aria-current="page" @endif>
                    <i class="bi bi-people"></i> Administrators
                </a>
            @endif
            @if ($admin->isSuperAdmin())
                <a href="{{ route('admin.settings.index') }}" class="{{ $linkActive('admin.settings.*') }}" @if(request()->routeIs('admin.settings.*')) aria-current="page" @endif>
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="{{ route('github.deploy') }}" class="{{ $linkActive('github.deploy') }}" @if(request()->routeIs('github.deploy')) aria-current="page" @endif>
                    <i class="bi bi-github"></i> GitHub Deploy
                </a>
            @endif
        @endif
    </nav>
</aside>
