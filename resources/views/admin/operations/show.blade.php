@extends('admin.layouts.app')
@section('title', 'Operation')
@section('crumb', 'Logs')
@section('content')
<div class="page-head">
    <h1>{{ $operation->intent ?: 'Operation' }}</h1>
    <p>{{ $operation->tenant->name }} · {{ $operation->status }} · correlation {{ $operation->correlation_id }}</p>
</div>
<div class="gk-two">
    <section class="gk-card">
        <h2 class="block-title">Parameters</h2>
        <pre class="mb-0">{{ json_encode($operation->parameters, JSON_PRETTY_PRINT) }}</pre>
    </section>
    <section class="gk-card">
        <h2 class="block-title">State history</h2>
        @foreach ($operation->history as $entry)
            <div class="field-row"><span>{{ $entry->from_status ?: 'start' }} → {{ $entry->to_status }}</span><span class="muted">{{ $entry->reason }}</span></div>
        @endforeach
    </section>
</div>
<div class="gk-card mt-3">
    <h2 class="block-title">Executions</h2>
    @forelse ($operation->executions as $execution)
        <pre class="mb-2">{{ json_encode($execution->response_data, JSON_PRETTY_PRINT) }}</pre>
    @empty
        <p class="mb-0">No connector execution has been confirmed.</p>
    @endforelse
    <form class="mt-3" method="post" action="{{ route('admin.operations.reconcile', $operation) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Reconcile</button></form>
</div>
@endsection
