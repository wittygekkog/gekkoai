<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="GekkoAi operations workspace for WittyGekko business units.">
    <title>@yield('title', 'Operations') — GekkoAi</title>
    <link rel="icon" href="{{ asset('images/gekkoai-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/gekko.css') }}">
    @php
        $themeColor = function (string $slug, string $fallback): string {
            $value = (string) getSetting($slug, $fallback);
            return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : $fallback;
        };
    @endphp
    <style>
        :root {
            --gk-primary: {{ $themeColor('theme-primary', '#7c3aed') }};
            --gk-sidebar: {{ $themeColor('theme-sidebar-bg', '#14122c') }};
            --gk-body: {{ $themeColor('theme-body-bg', '#f4f6fb') }};
        }
    </style>
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('gk-theme') || 'light');
    </script>
</head>
<body>
@php($admin = auth('admin')->user())
<div class="gk-shell">
    @include('admin.partials.sidebar')
    <div class="gk-main">
        @include('admin.partials.topbar')
        <main class="gk-content">
            @if (session('success'))
                <div class="alert alert-success border">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<div class="modal fade" id="adminLogoutModal" tabindex="-1" aria-labelledby="adminLogoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="gk-logout-icon mb-3"><i class="bi bi-box-arrow-right"></i></div>
                <h2 class="h6 fw-bold mb-1" id="adminLogoutModalLabel">Sign out?</h2>
                <p class="small text-muted mb-3">You will need your username and password to sign back in.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="{{ route('admin.logout') }}" class="flex-fill">@csrf<button type="submit" class="btn btn-gk w-100">Sign out</button></form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/alpine/alpine.min.js') }}" defer></script>
<script>
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('gk-theme', next);
        });
    });
    var menu = document.getElementById('sidebar');
    document.querySelectorAll('[data-menu]').forEach(function (button) {
        button.addEventListener('click', function () { menu.classList.toggle('show'); });
    });
    if (menu) {
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { menu.classList.remove('show'); });
        });
    }
    document.querySelectorAll('form.search-form select').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });
    document.querySelectorAll('[data-tabs]').forEach(function (nav) {
        var key = 'gk-tab-' + nav.dataset.tabs;
        var saved = localStorage.getItem(key);
        if (saved) {
            var trigger = nav.querySelector('[data-bs-target="' + saved + '"]');
            if (trigger && window.bootstrap) { bootstrap.Tab.getOrCreateInstance(trigger).show(); }
        }
        nav.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function (event) {
                localStorage.setItem(key, event.target.getAttribute('data-bs-target'));
            });
        });
    });
</script>
@stack('scripts')
</body>
</html>
