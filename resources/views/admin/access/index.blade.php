@extends('admin.layouts.app')
@section('title', 'Administrators')
@section('crumb', 'Administrators')
@section('content')
<div class="page-head d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div><h1>{{ $role === 'superadmin' ? 'Super Admins' : ($role === 'admin' ? 'Admins' : 'Administrators') }}</h1><p>Superadmins manage the platform. Assigned accounts only see their SBUs.</p></div>
    <a class="btn btn-gk" href="{{ route('admin.access.create') }}">Add administrator</a>
</div>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Username</th><th class="fit">Type</th><th class="fit">Scope</th><th class="fit">Status</th><th class="fit"></th></tr></thead>
        <tbody>
        @foreach ($admins as $account)
            @php($labels = [0 => 'Pending', 1 => 'Active', 2 => 'Archived', 3 => 'Deleted'])
            <tr>
                <td>{{ $account->username }}<div class="muted">{{ $account->name }}</div></td>
                <td class="fit">{{ $account->admin_type === 0 ? 'Superadmin' : 'Admin' }}</td>
                <td class="fit">{{ $account->access_scope }}</td>
                <td class="fit">{{ $labels[$account->status] ?? $account->status }}</td>
                <td class="fit"><a href="{{ route('admin.access.edit', $account) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $admins->links('pagination::bootstrap-5') }}</div>
@endsection
