@extends('admin.layouts.app')
@section('title', 'SBUs')
@section('crumb', 'SBUs')
@section('content')
<div class="page-head d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div><h1>Business units</h1><p>Each SBU keeps its own application. This platform only stores the connector.</p></div>
    @if (auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->access_scope === 'platform')
        <a class="btn btn-gk" href="{{ route('admin.tenants.create') }}">Add SBU</a>
    @endif
</div>
<form class="search-form mb-3" method="get">
    <input class="form-control" style="max-width: 320px;" name="q" value="{{ $q }}" placeholder="Search name" aria-label="Search name">
</form>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Name</th><th class="fit">Connector</th><th class="fit">Status</th><th class="fit"></th></tr></thead>
        <tbody>
        @forelse ($items as $tenant)
            <tr>
                <td><a href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a><div class="muted">{{ $tenant->slug }}</div></td>
                <td class="fit">{{ $tenant->connector->mode ?? 'missing' }}</td>
                <td class="fit">{{ $tenant->status }}</td>
                <td class="fit"><a href="{{ route('admin.tenants.edit', $tenant) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="4">No business units are visible to this account.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $items->links('pagination::bootstrap-5') }}</div>
@endsection
