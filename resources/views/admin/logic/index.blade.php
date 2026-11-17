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
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle gk-table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                @if ($tenants->count() !== 1)<th>SBU</th>@endif
                                <th>Statement</th>
                                <th class="fit">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($rules as $rule)
                            <tr>
                                <td>{{ $rule->title }}</td>
                                @if ($tenants->count() !== 1)<td>{{ $rule->tenant->name }}</td>@endif
                                <td class="text-truncate" style="max-width: 28rem;" title="{{ $rule->body }}">{{ $rule->body }}</td>
                                <td class="fit"><span class="badge-soft badge-ok">{{ $rule->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $tenants->count() === 1 ? 3 : 4 }}">No rules yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
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

@if ($errors->any() && old('kind'))
@push('scripts')
<script>
    var logicTab = document.querySelector('[data-bs-target="#logic-{{ old('kind') === 'instruction' ? 'instructions' : 'rules' }}"]');
    if (logicTab && window.bootstrap) {
        bootstrap.Tab.getOrCreateInstance(logicTab).show();
    }
</script>
@endpush
@endif
