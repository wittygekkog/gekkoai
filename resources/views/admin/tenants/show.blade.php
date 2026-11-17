@extends('admin.layouts.app')
@section('title', $tenant->name)
@section('crumb', 'SBUs')
@section('content')
<div class="page-head d-flex justify-content-between gap-3 flex-wrap">
    <div>
        <h1>{{ $tenant->name }}</h1>
        <p>{{ $tenant->description }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.tenants.edit', $tenant) }}">Edit</a>
</div>
<div class="gk-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
    <section class="gk-card">
        <h2 class="block-title">Connector</h2>
        <div class="field-row"><span>Mode</span><strong>{{ $tenant->connector->mode ?? 'missing' }}</strong></div>
        <div class="field-row"><span>Base URL</span><strong>{{ $tenant->connector->base_url ?? '—' }}</strong></div>
        <div class="field-row"><span>Status</span><strong>{{ $tenant->connector->status ?? '—' }}</strong></div>
        <p class="muted mb-0 mt-2">Simulation does not call the SBU application or its database.</p>
    </section>
    <section class="gk-card">
        <h2 class="block-title">Registered operations</h2>
        @forelse ($tenant->operations as $operation)
            <div class="field-row">
                <span>{{ $operation->operation_name }}</span>
                <span class="badge-soft {{ $operation->enabled ? 'badge-ok' : 'badge-bad' }}">{{ $operation->risk_level }}</span>
            </div>
        @empty
            <p class="mb-0">No operations are enabled for this SBU yet.</p>
        @endforelse
    </section>
</div>
<section class="gk-card mt-3">
    <h2 class="block-title">Platform catalog</h2>
    <p class="muted">These definitions live in code. A model cannot add or downgrade them.</p>
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Operation</th><th>Risk</th><th class="fit">Approval</th><th class="fit">Read only</th></tr></thead>
        <tbody>
        @foreach ($catalog as $definition)
            <tr>
                <td>{{ $definition->name }}<div class="muted">{{ $definition->description }}</div></td>
                <td>{{ $definition->risk }}</td>
                <td class="fit">{{ $definition->approvalRequired ? 'Required' : 'No' }}</td>
                <td class="fit">{{ $definition->readOnly ? 'Yes' : 'No' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endsection
