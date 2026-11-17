@extends('admin.layouts.app')
@section('title', 'Learning')
@section('crumb', 'Learning Center')
@section('content')
<div class="page-head d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div><h1>Learning center</h1><p>Corrections stay pending until a reviewer approves them. They never override an approval policy.</p></div>
    <form method="post" action="{{ route('admin.learning.evaluate') }}">@csrf<button class="btn btn-gk" type="submit">Run evaluation</button></form>
</div>
<div class="gk-two">
    <section class="gk-card">
        <h2 class="block-title">Examples</h2>
        @forelse ($examples as $example)
            <div class="field-row">
                <span>{{ \Illuminate\Support\Str::limit($example->input_text, 80) }}</span>
                @if ($example->approval_status !== 'approved')
                    <form method="post" action="{{ route('admin.learning.approve', $example) }}">@csrf<button class="btn btn-sm btn-gk" type="submit">Approve</button></form>
                @else
                    <span class="badge-soft badge-ok">Approved</span>
                @endif
            </div>
        @empty
            <p class="mb-0">No learning examples yet.</p>
        @endforelse
        <div class="mt-3">{{ $examples->links('pagination::bootstrap-5') }}</div>
    </section>
    <section class="gk-card">
        <h2 class="block-title">Recent evaluations</h2>
        @forelse ($runs as $run)
            <div class="field-row"><span>{{ $run->dataset_version }}</span><span>{{ $run->metrics['intent_accuracy'] ?? '—' }}</span></div>
        @empty
            <p>No evaluation has been recorded. Automatic execution stays off.</p>
        @endforelse
        <h2 class="block-title">Recent feedback</h2>
        @forelse ($feedback as $item)
            <div class="field-row"><span>{{ $item->issue_type ?: 'Feedback' }}</span><span class="num">{{ $item->rating }}/5</span></div>
        @empty
            <p class="mb-0">No feedback yet.</p>
        @endforelse
    </section>
</div>
@endsection
