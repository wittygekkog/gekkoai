@extends('admin.layouts.app')
@section('title', 'Conversations')
@section('crumb', 'Conversations')
@section('content')
<div class="page-head"><h1>Conversations</h1><p>Tenant-scoped instruction history.</p></div>
<form class="search-form mb-3" method="get">
    <select name="status" class="form-select" style="max-width: 240px;" aria-label="Status">
        <option value="">All statuses</option>
        @foreach (['open', 'closed'] as $option)
            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
        @endforeach
    </select>
</form>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Title</th><th>SBU</th><th class="fit">Updated</th></tr></thead>
        <tbody>
        @forelse ($conversations as $conversation)
            <tr>
                <td><a href="{{ route('admin.workspace.show', $conversation) }}">{{ $conversation->title }}</a></td>
                <td>{{ $conversation->tenant->name }}</td>
                <td class="fit">{{ $conversation->updated_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No conversations yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $conversations->links('pagination::bootstrap-5') }}</div>
@endsection
