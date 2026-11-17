@extends('admin.layouts.app')
@section('title', $account->exists ? 'Edit administrator' : 'New administrator')
@section('crumb', 'Administrators')
@section('content')
<div class="gk-card" style="max-width: 720px;">
    <h1 class="h4">{{ $account->exists ? $account->username : 'New administrator' }}</h1>
    <form method="post" action="{{ $account->exists ? route('admin.access.update', $account) : route('admin.access.store') }}">
        @csrf
        @if ($account->exists) @method('PATCH') @endif
        <label class="form-label" for="name">Name</label>
        <input id="name" name="name" class="form-control mb-3" value="{{ old('name', $account->name) }}" required>
        <label class="form-label" for="username">Username</label>
        <input id="username" name="username" class="form-control mb-3" value="{{ old('username', $account->username) }}" required>
        <label class="form-label" for="email">Email</label>
        <input id="email" name="email" type="email" class="form-control mb-3" value="{{ old('email', $account->email) }}" required>
        <label class="form-label" for="password">Password{{ $account->exists ? ' (leave blank to keep)' : '' }}</label>
        <input id="password" name="password" type="password" class="form-control mb-3" minlength="6" @required(! $account->exists) autocomplete="new-password">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="admin_type">Role</label>
                <select id="admin_type" name="admin_type" class="form-select">
                    @if (auth('admin')->user()->isSuperAdmin())
                        <option value="0" @selected((string) old('admin_type', $account->admin_type) === '0')>Superadmin</option>
                    @endif
                    <option value="1" @selected((string) old('admin_type', $account->admin_type) === '1')>Admin</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="access_scope">Scope</label>
                <select id="access_scope" name="access_scope" class="form-select">
                    <option value="platform" @selected(old('access_scope', $account->access_scope) === 'platform')>Platform</option>
                    <option value="assigned" @selected(old('access_scope', $account->access_scope) === 'assigned')>Assigned SBU</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    @foreach ([0 => 'Pending', 1 => 'Active', 2 => 'Archived', 3 => 'Deleted'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('status', $account->status) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label" for="tenant_id">Assigned SBU</label>
                <select id="tenant_id" name="tenant_id" class="form-select">
                    <option value="">None</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tenant_role">SBU role</label>
                <select id="tenant_role" name="tenant_role" class="form-select">
                    <option value="">None</option>
                    @foreach (['sbu_administrator' => 'SBU administrator', 'sbu_operator' => 'SBU operator', 'sbu_viewer' => 'SBU viewer', 'human_reviewer' => 'Human reviewer'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
        @endif
        <button class="btn btn-gk mt-3" type="submit">Save</button>
    </form>
    @if ($account->exists)
        <form class="mt-3" method="post" action="{{ route('admin.access.destroy', $account) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger" type="submit">Delete</button>
        </form>
    @endif
</div>
@endsection
