<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — GekkoAi</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/gekko.css') }}">
</head>
<body>
<div class="login-shell">
    <section class="login-hero">
        <img src="{{ asset('images/gekkoai-logo.png') }}" alt="GekkoAi" style="width: 210px; background: #fff; border-radius: 16px; padding: 8px 12px;">
        <h1>One AI workspace.<br>Every business unit.</h1>
        <div class="feature"><i class="bi bi-lightning-charge"></i><div><strong>Build and deploy faster</strong><div>Turn instructions into checked operations.</div></div></div>
        <div class="feature"><i class="bi bi-people"></i><div><strong>Empower every team</strong><div>Each business unit keeps its own rules and data.</div></div></div>
        <div class="feature"><i class="bi bi-shield-check"></i><div><strong>Enterprise-grade control</strong><div>Approval, audit, and tenant isolation before any write.</div></div></div>
    </section>
    <section class="login-panel">
        <div class="gk-card login-card">
            <h2 class="mb-1">Welcome back</h2>
            <p class="muted mb-4">Sign in to GekkoAi Operations</p>
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            <form method="post" action="{{ route('admin.login.store') }}">
                @csrf
                <label class="form-label" for="username">Username</label>
                <input id="username" name="username" value="{{ old('username') }}" class="form-control mb-3" autocomplete="username" required>
                <label class="form-label" for="password">Password</label>
                <input id="password" name="password" type="password" class="form-control mb-3" autocomplete="current-password" required>
                <div class="d-flex justify-content-between mb-3">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1"> Remember me</label>
                </div>
                <button class="btn btn-gk w-100" type="submit">Sign in</button>
            </form>
            <p class="muted mt-3 mb-0">Protected by an encrypted session, role checks, and approval gates.</p>
        </div>
    </section>
</div>
</body>
</html>
