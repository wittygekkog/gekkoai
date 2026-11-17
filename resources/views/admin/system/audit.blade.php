@extends('admin.layouts.app')
@section('title', 'Audit')
@section('crumb', 'Audit Logs')
@section('content')
<div class="page-head"><h1>Audit logs</h1><p>State changes and connector calls. Secrets are not written here.</p></div>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th class="fit">When</th><th>Event</th><th>Resource</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="fit">{{ $log->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                <td>{{ $log->event_type }}</td>
                <td>{{ $log->resource_type }} {{ $log->resource_id }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No audit events yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
