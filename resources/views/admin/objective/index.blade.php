@extends('admin.layouts.app')
@section('title', 'Objective')
@section('crumb', 'Objective')
@section('content')
<div class="page-head d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div class="min-w-0">
        <h1>Objective</h1>
        <p>The outcome this SBU is working toward, in one sentence. It does not grant a tool or override Logic.</p>
    </div>
    @if ($canManage)
        <button class="btn btn-gk flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#objectiveChat" data-objective-mode="create">Add an objective</button>
    @endif
</div>

<section class="gk-card gk-list-card">
    @if ($objectives->isEmpty())
        <p class="mb-0">No objective yet.</p>
    @else
        <div class="accordion gk-accordion" id="objectiveAccordion">
            @foreach ($objectives as $objective)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="objective-heading-{{ $objective->id }}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#objective-panel-{{ $objective->id }}" aria-expanded="false" aria-controls="objective-panel-{{ $objective->id }}">
                            <span class="gk-acc-title">{{ $objective->title }}</span>
                            @if ($tenants->count() !== 1)
                                <span class="gk-acc-meta">{{ $objective->tenant->name }}</span>
                            @endif
                            <span class="badge-soft badge-ok gk-acc-status">{{ $objective->status }}</span>
                        </button>
                    </h3>
                    <div id="objective-panel-{{ $objective->id }}" class="accordion-collapse collapse" data-bs-parent="#objectiveAccordion" aria-labelledby="objective-heading-{{ $objective->id }}">
                        <div class="accordion-body">
                            <div class="gk-rule-row">
                                <p>{{ $objective->body }}</p>
                                @if ($canManage)
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#objectiveChat" data-objective-mode="edit" data-objective="{{ json_encode(['id' => $objective->id, 'tenant_id' => $objective->tenant_id, 'title' => $objective->title, 'body' => $objective->body], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">Edit</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    @if ($objectives->hasPages())
        <div class="mt-2">{{ $objectives->links('pagination::bootstrap-5') }}</div>
    @endif
</section>

@if ($canManage)
    @include('admin.objective.chat')
@endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('#objectiveAccordion .accordion-collapse').forEach(function (panel) {
        var header = panel.previousElementSibling;
        panel.addEventListener('shown.bs.collapse', function () {
            if (header) header.classList.add('is-open');
        });
        panel.addEventListener('hide.bs.collapse', function () {
            if (header) header.classList.remove('is-open');
        });
    });
</script>
@if ($canManage)
    @include('admin.objective.chat-script')
@endif
@endpush
