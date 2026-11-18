@extends('admin.layouts.app')
@section('title', 'Logic')
@section('crumb', 'Logic')
@section('content')
<div class="page-head d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div class="min-w-0">
        <h1>Logic</h1>
        <p>Rules the workspace must follow. A rule does not grant a tool or skip approval.</p>
    </div>
    @if ($canManage)
        <button class="btn btn-gk flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#logicChat" data-logic-mode="create">Add a rule</button>
    @endif
</div>

<section class="gk-card gk-list-card">
    @if ($rules->isEmpty())
        <p class="mb-0">No rules yet.</p>
    @else
        <div class="accordion gk-accordion" id="logicRulesAccordion">
            @foreach ($rules as $rule)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="rule-heading-{{ $rule->id }}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rule-panel-{{ $rule->id }}" aria-expanded="false" aria-controls="rule-panel-{{ $rule->id }}">
                            <span class="gk-acc-title">{{ $rule->title }}</span>
                            @if ($tenants->count() !== 1)
                                <span class="gk-acc-meta">{{ $rule->tenant->name }}</span>
                            @endif
                            <span class="badge-soft badge-ok gk-acc-status">{{ $rule->status }}</span>
                        </button>
                    </h3>
                    <div id="rule-panel-{{ $rule->id }}" class="accordion-collapse collapse" data-bs-parent="#logicRulesAccordion" aria-labelledby="rule-heading-{{ $rule->id }}">
                        <div class="accordion-body">
                            <div class="gk-rule-row">
                                <p>{{ $rule->body }}</p>
                                @if ($canManage)
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#logicChat" data-logic-mode="edit" data-rule="{{ json_encode(['id' => $rule->id, 'tenant_id' => $rule->tenant_id, 'title' => $rule->title, 'body' => $rule->body], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">Edit</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    @if ($rules->hasPages())
        <div class="mt-2">{{ $rules->links('pagination::bootstrap-5') }}</div>
    @endif
</section>

@if ($canManage)
    @include('admin.logic.chat')
@endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('#logicRulesAccordion .accordion-collapse').forEach(function (panel) {
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
    @include('admin.logic.chat-script')
@endif
@endpush
