@extends('admin.layouts.app')
@section('title', 'Flags')
@section('crumb', 'Flags')
@section('content')
<div class="page-head"><h1>Flags</h1><p>Unresolved, unsupported, or sensitive requests.</p></div>
<div class="gk-card">
    @forelse ($escalations as $escalation)
        <article class="gate-card mb-3">
            <strong>{{ $escalation->tenant->name }}</strong>
            <div>{{ $escalation->reason }} · {{ $escalation->status }}</div>
            @if ($escalation->status === 'open')
                <form method="post" action="{{ route('admin.escalations.resolve', $escalation) }}" class="mt-2">
                    @csrf
                    <textarea class="form-control" name="resolution" required placeholder="Resolution"></textarea>
                    <button class="btn btn-gk mt-2" type="submit">Resolve</button>
                </form>
            @else
                <p class="mb-0">{{ $escalation->resolution }}</p>
            @endif
        </article>
    @empty
        <p class="mb-0">No escalations.</p>
    @endforelse
</div>
<div class="mt-3">{{ $escalations->links('pagination::bootstrap-5') }}</div>
@endsection
