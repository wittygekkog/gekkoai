@extends('admin.layouts.app')
@section('title', 'Connectors')
@section('crumb', 'SBU Connectors')
@section('content')
<div class="page-head"><h1>SBU connectors</h1><p>Health checks on simulation connectors do not contact the business application.</p></div>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>SBU</th><th>Base URL</th><th class="fit">Mode</th><th class="fit">Status</th><th class="fit"></th></tr></thead>
        <tbody>
        @forelse ($connectors as $connector)
            <tr>
                <td>{{ $connector->tenant->name }}</td>
                <td>{{ $connector->base_url }}</td>
                <td class="fit">{{ $connector->mode }}</td>
                <td class="fit">{{ $connector->status }}</td>
                <td class="fit">
                    <form method="post" action="{{ route('admin.connectors.health', $connector) }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Check</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No connectors.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
