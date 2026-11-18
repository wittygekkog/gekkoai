@extends('admin.layouts.app')
@section('title', 'Logic')
@section('crumb', 'Logic')
@section('content')
<div class="page-head">
    <h1>Logic</h1>
    <p>Rules the workspace must follow, and instructions that show how a request should be read. Neither one grants a tool or skips approval.</p>
</div>

<ul class="nav nav-tabs" data-tabs="logic" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active py-3" id="logic-rules-tab" data-bs-toggle="tab" data-bs-target="#logic-rules" type="button" role="tab" aria-controls="logic-rules" aria-selected="true">Rules</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="logic-instructions-tab" data-bs-toggle="tab" data-bs-target="#logic-instructions" type="button" role="tab" aria-controls="logic-instructions" aria-selected="false">Instructions</button>
    </li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="logic-rules" role="tabpanel" aria-labelledby="logic-rules-tab">
        <div class="gk-two">
            <section class="gk-card">
                <h2 class="block-title">Rules</h2>
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
                                    <div class="accordion-body">{{ $rule->body }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="mt-3">{{ $rules->links('pagination::bootstrap-5') }}</div>
            </section>
            @if ($canManage)
                @include('admin.logic.form', ['kind' => 'rule', 'heading' => 'Add a rule', 'bodyLabel' => 'Statement'])
            @endif
        </div>
    </div>

    <div class="tab-pane fade" id="logic-instructions" role="tabpanel" aria-labelledby="logic-instructions-tab">
        <div class="gk-two">
            <section class="gk-card">
                <h2 class="block-title">Instructions</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle gk-table mb-0">
                        <thead>
                            <tr>
                                <th>Example</th>
                                <th class="fit">Intent</th>
                                @if ($tenants->count() !== 1)<th>SBU</th>@endif
                                <th class="fit">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($instructions as $instruction)
                            <tr>
                                <td class="text-truncate" style="max-width: 28rem;" title="{{ $instruction->body }}">{{ $instruction->body }}</td>
                                <td class="fit">{{ $instruction->intent ?: '—' }}</td>
                                @if ($tenants->count() !== 1)<td>{{ $instruction->tenant->name }}</td>@endif
                                <td class="fit"><span class="badge-soft badge-info">{{ $instruction->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $tenants->count() === 1 ? 3 : 4 }}">No instructions yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $instructions->links('pagination::bootstrap-5') }}</div>
            </section>
            @if ($canManage)
                @include('admin.logic.form', ['kind' => 'instruction', 'heading' => 'Add an instruction', 'bodyLabel' => 'Instruction'])
            @endif
        </div>
    </div>
</div>
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
    @if ($errors->any() && old('kind'))
    var logicTab = document.querySelector('[data-bs-target="#logic-{{ old('kind') === 'instruction' ? 'instructions' : 'rules' }}"]');
    if (logicTab && window.bootstrap) {
        bootstrap.Tab.getOrCreateInstance(logicTab).show();
    }
    @endif
</script>
@endpush
