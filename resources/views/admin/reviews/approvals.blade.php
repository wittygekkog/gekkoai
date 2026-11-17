@extends('admin.layouts.app')
@section('title', 'Approvals')
@section('crumb', 'Pending Approvals')
@section('content')
<div class="page-head"><h1>Pending approvals</h1><p>An approval is valid only for the exact parameter snapshot.</p></div>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Operation</th><th>SBU</th><th class="fit">Status</th><th class="fit">Expires</th><th></th></tr></thead>
        <tbody>
        @forelse ($approvals as $approval)
            <tr>
                <td>{{ $approval->operation->intent ?? '—' }}</td>
                <td>{{ $approval->operation->tenant->name ?? '—' }}</td>
                <td class="fit">{{ $approval->status }}</td>
                <td class="fit">{{ optional($approval->expires_at)->diffForHumans() }}</td>
                <td>
                    @if ($approval->status === 'pending')
                        <form method="post" action="{{ route('admin.approvals.approve', $approval) }}" class="d-flex gap-2">
                            @csrf
                            <input class="form-control" name="reason" placeholder="Note">
                            <button class="btn btn-gk" type="submit">Approve</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No approvals.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $approvals->links('pagination::bootstrap-5') }}</div>
@endsection
