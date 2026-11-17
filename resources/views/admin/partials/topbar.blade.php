{{-- Top bar follows the reference admin header: page title on the left, icon tools and the account chip on the right. --}}
@php
    $displayName = trim((string) ($admin->name ?: $admin->username));
    $initials = collect(preg_split('/\s+/', $displayName))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $firstName = \Illuminate\Support\Str::before($displayName, ' ');
    $canManageAdmins = $admin->isSuperAdmin() || ($admin->admin_type === 1 && $admin->access_scope === 'platform');
@endphp
<header class="gk-topbar">
    <button class="btn gk-icon-btn menu-btn" type="button" data-menu aria-label="Open menu"><i class="bi bi-list"></i></button>
    <h1 class="gk-page-heading">@yield('title', 'Operations')</h1>
    <div class="gk-page-actions">@yield('actions')</div>
    <span class="gk-topbar-divider d-none d-md-block" aria-hidden="true"></span>
    <div class="gk-top-actions">
        @if ($admin->isSuperAdmin())
            <a class="btn gk-icon-btn d-none d-md-inline-flex {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}" title="Settings" aria-label="Settings"><i class="bi bi-gear"></i></a>
        @endif
        @if ($canManageAdmins)
            <div class="dropdown">
                <button class="btn gk-icon-btn {{ request()->routeIs('admin.access.*') ? 'active' : '' }}" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Access" title="Access"><i class="bi bi-shield-lock"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header">Access</h6></li>
                    @if ($admin->isSuperAdmin())
                        <li><a class="dropdown-item" href="{{ route('admin.access.index', ['role' => 'superadmin']) }}"><i class="bi bi-person-fill-gear"></i> Super Admins</a></li>
                    @endif
                    <li><a class="dropdown-item" href="{{ route('admin.access.index', ['role' => 'admin']) }}"><i class="bi bi-person-gear"></i> Admins</a></li>
                </ul>
            </div>
        @endif
        <a class="btn gk-icon-btn" href="{{ route('admin.escalations.index') }}" title="Flags" aria-label="Flags"><i class="bi bi-bell"></i></a>
        <button class="btn gk-icon-btn" type="button" data-theme-toggle aria-label="Toggle color theme" title="Toggle color theme"><i class="bi bi-moon"></i></button>
        <div class="dropdown">
            <button class="btn gk-user-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                <span class="gk-avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'A' }}</span>
                <span class="gk-user-name">Hi, {{ $firstName }}</span>
                <i class="bi bi-chevron-down small gk-user-name"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2">
                    <div class="fw-semibold">{{ $displayName }}</div>
                    <div class="small text-muted">{{ '@'.$admin->username }} · {{ $admin->isSuperAdmin() ? 'Superadmin' : 'Admin' }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                @if ($admin->isSuperAdmin())
                    <li class="d-md-none"><a class="dropdown-item" href="{{ route('admin.settings.index') }}"><i class="bi bi-gear"></i> Settings</a></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('admin.password.edit') }}"><i class="bi bi-key"></i> Change password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#adminLogoutModal"><i class="bi bi-box-arrow-right"></i> Sign out</button></li>
            </ul>
        </div>
    </div>
</header>
