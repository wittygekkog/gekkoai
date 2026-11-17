@extends('admin.layouts.app')
@section('title', 'Logs')
@section('crumb', 'Logs')
@section('content')
<div class="page-head"><h1>Logs</h1><p>Every instruction the platform prepared or ran.</p></div>
<form class="search-form mb-3" method="get">
    <select name="status" class="form-select" style="max-width: 280px;" aria-label="Status">
        <option value="">All statuses</option>
        @foreach (['awaiting_clarification','awaiting_approval','completed','failed','escalated','unknown_outcome','cancelled'] as $option)
            <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', $option) }}</option>
        @endforeach
    </select>
</form>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>SBU</th><th>Intent</th><th class="num">Score</th><th class="fit">Status</th><th class="fit"></th></tr></thead>
        <tbody>
        @forelse ($operations as $operation)
            <tr>
                <td>{{ $operation->tenant->name }}</td>
                <td>{{ $operation->intent ?: '—' }}</td>
                <td class="num">{{ $operation->confidence_score !== null ? number_format($operation->confidence_score * 100, 0).'%' : '—' }}</td>
                <td class="fit">{{ str_replace('_', ' ', $operation->status) }}</td>
                <td class="fit"><a href="{{ route('admin.operations.show', $operation) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="5">No operations yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $operations->links('pagination::bootstrap-5') }}</div>
@endsection
