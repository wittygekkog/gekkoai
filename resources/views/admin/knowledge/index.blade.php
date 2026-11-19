@extends('admin.layouts.app')
@section('title', 'Data')
@section('crumb', 'Data')
@section('content')
@php
    $columns = $selectedTenant ? 4 : 5;
@endphp
<div class="page-head d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div class="min-w-0">
        <h1>Data</h1>
        @if ($selectedTenant)
            <p>Documents for {{ $selectedTenant->name }}. Only approved documents can be retrieved, and they cannot grant tools.</p>
        @else
            <p>Select an SBU in the workspace menu to add a document. This list shows every SBU you can access.</p>
        @endif
    </div>
    @if ($canManage)
        <button class="btn btn-gk flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#knowledgeFormCanvas" data-knowledge-mode="create">Add a document</button>
    @endif
</div>

<form class="search-form gk-list-toolbar" method="get">
    <input class="form-control" name="q" value="{{ $q }}" placeholder="Search title" aria-label="Search title" maxlength="180">
    <select class="form-select" name="status" aria-label="Status">
        <option value="">All statuses</option>
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-secondary" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
    <span class="gk-list-count">{{ $documents->total() }}</span>
</form>

<section class="gk-card gk-list-card">
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle gk-table gk-doc-table mb-0">
            <thead>
                <tr>
                    <th class="gk-col-grow">Title</th>
                    @unless ($selectedTenant)
                        <th class="gk-col-sbu">SBU</th>
                    @endunless
                    <th class="fit">Updated</th>
                    <th class="fit">Status</th>
                    <th class="fit"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($documents as $document)
                @php
                    $hasFile = is_string($document->source_reference) && str_starts_with($document->source_reference, 'knowledge/');
                    $excerpt = \Illuminate\Support\Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $document->content)), 160);
                    $statusClass = match ($document->approval_status) {
                        'approved' => 'badge-ok',
                        'pending' => 'badge-wait',
                        'rejected' => 'badge-bad',
                        default => 'badge-info',
                    };
                    $kind = match ($document->source_type) {
                        'file' => 'File',
                        'text' => 'Text',
                        'seed' => 'Seed',
                        default => ucfirst((string) $document->source_type),
                    };
                @endphp
                <tr>
                    <td class="gk-col-grow">
                        <div class="gk-doc-title" title="{{ $document->title }}">{{ $document->title }}</div>
                        @if ($excerpt !== '')
                            <div class="gk-doc-excerpt" title="{{ $excerpt }}">{{ $excerpt }}</div>
                        @endif
                    </td>
                    @unless ($selectedTenant)
                        <td class="gk-col-sbu" title="{{ $document->tenant->name }}"><span>{{ $document->tenant->name }}</span></td>
                    @endunless
                    <td class="fit gk-col-when" title="{{ $document->updated_at?->toDayDateTimeString() }}">
                        <div>{{ $document->updated_at?->format('M j, Y') }}</div>
                        <div class="gk-doc-kind">{{ $kind }}</div>
                    </td>
                    <td class="fit gk-col-status"><span class="badge-soft {{ $statusClass }}">{{ ucfirst($document->approval_status) }}</span></td>
                    <td class="fit gk-col-actions">
                        @if ($manageableTenantIds->contains($document->tenant_id) || $hasFile)
                            <div class="gk-row-actions">
                                @if ($hasFile)
                                    <a class="gk-icon-btn gk-icon-btn-line" href="{{ route('admin.knowledge.file', $document) }}" aria-label="Download file" title="Download file"><i class="bi bi-download"></i></a>
                                @endif
                                @if ($manageableTenantIds->contains($document->tenant_id))
                                    <button class="gk-icon-btn gk-icon-btn-line" type="button" data-bs-toggle="offcanvas" data-bs-target="#knowledgeFormCanvas" data-knowledge-mode="edit" aria-label="Edit" title="Edit" data-document="{{ json_encode(['id' => $document->id, 'title' => $document->title, 'content' => $document->content, 'tenant' => $document->tenant->name, 'file' => $hasFile ? route('admin.knowledge.file', $document) : null], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"><i class="bi bi-pencil"></i></button>
                                    @if ($document->approval_status !== 'approved')
                                        <form method="post" action="{{ route('admin.knowledge.approve', $document) }}">@csrf<button class="gk-icon-btn gk-icon-btn-line" type="submit" aria-label="Approve" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                                    @endif
                                    <form method="post" action="{{ route('admin.knowledge.destroy', $document) }}" onsubmit="return confirm('Delete this document?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="gk-icon-btn gk-icon-btn-line text-danger" type="submit" aria-label="Delete" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $columns }}">No documents match this list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($documents->hasPages())
        <div class="gk-list-pages">{{ $documents->links('pagination::bootstrap-5') }}</div>
    @endif
</section>

@if ($manageableTenantIds->isNotEmpty())
    <div class="offcanvas offcanvas-end gk-drawer" tabindex="-1" id="knowledgeFormCanvas" aria-labelledby="knowledgeFormTitle">
        <div class="offcanvas-header">
            <div class="min-w-0">
                <h2 class="offcanvas-title h5 mb-0" id="knowledgeFormTitle">Add a document</h2>
                <p class="drawer-note" id="knowledgeScope">{{ $selectedTenant ? 'Saved for '.$selectedTenant->name.'.' : '' }}</p>
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form id="knowledgeForm" class="gk-drawer-body" method="post" action="{{ route('admin.knowledge.store') }}" enctype="multipart/form-data">
                @csrf
                <input id="knowledgeMethod" type="hidden" name="_method" value="PATCH" @disabled(! $errors->any() || ! old('document_id'))>
                <input id="knowledgeDocumentId" type="hidden" name="document_id" value="{{ $errors->any() ? old('document_id') : '' }}">
                <div class="gk-drawer-main">
                    @if ($errors->any())
                        <div class="alert alert-danger border mb-0">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif
                    <div>
                        <label class="form-label" for="knowledgeTitle">Title</label>
                        <input id="knowledgeTitle" name="title" class="form-control" value="{{ $errors->any() ? old('title') : '' }}" required maxlength="180">
                    </div>
                    <div class="gk-drawer-grow">
                        <label class="form-label" for="knowledgeContent">Text</label>
                        <textarea id="knowledgeContent" name="content" class="form-control" rows="8" maxlength="50000">{{ $errors->any() ? old('content') : '' }}</textarea>
                    </div>
                    <div class="file-zone">
                        <label class="form-label" for="knowledgeFile">File</label>
                        <input id="knowledgeFile" name="file" type="file" class="form-control" accept=".txt,.md,.csv,.json,.doc,.docx,.pdf,text/plain,text/csv,application/json,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        <div class="form-text">TXT, MD, CSV, JSON, DOC, DOCX, or PDF. Maximum 10 MB. A text file fills the document when the text box is empty.</div>
                        <p class="form-text d-none mb-0" id="knowledgeCurrentFile"></p>
                    </div>
                </div>
                <div class="gk-drawer-foot">
                    <button class="btn btn-gk w-100" type="submit">Save document</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@if ($manageableTenantIds->isNotEmpty())
@push('scripts')
<script>
    (function () {
        var form = document.getElementById('knowledgeForm');
        var panel = document.getElementById('knowledgeFormCanvas');
        if (!form || !panel) return;
        var method = document.getElementById('knowledgeMethod');
        var title = document.getElementById('knowledgeTitle');
        var content = document.getElementById('knowledgeContent');
        var file = document.getElementById('knowledgeFile');
        var docId = document.getElementById('knowledgeDocumentId');
        var heading = document.getElementById('knowledgeFormTitle');
        var scope = document.getElementById('knowledgeScope');
        var current = document.getElementById('knowledgeCurrentFile');
        var storeUrl = form.getAttribute('action');
        var updateBase = @json(route('admin.knowledge.update', ['document' => '00000000-0000-4000-8000-000000000000']));
        var createScope = @json($selectedTenant ? 'Saved for '.$selectedTenant->name.'.' : '');

        function openCreate() {
            heading.textContent = 'Add a document';
            form.action = storeUrl;
            method.disabled = true;
            docId.value = '';
            title.value = '';
            content.value = '';
            file.value = '';
            scope.textContent = createScope;
            current.classList.add('d-none');
            current.replaceChildren();
        }

        function openEdit(doc) {
            heading.textContent = 'Edit document';
            form.action = updateBase.replace('00000000-0000-4000-8000-000000000000', doc.id);
            method.disabled = false;
            docId.value = doc.id;
            title.value = doc.title || '';
            content.value = doc.content || '';
            file.value = '';
            scope.textContent = 'Saved for ' + (doc.tenant || 'this SBU') + '. The SBU does not change.';
            current.replaceChildren();
            if (doc.file) {
                var link = document.createElement('a');
                link.href = doc.file;
                link.textContent = 'Download current file';
                current.appendChild(link);
                current.classList.remove('d-none');
            } else {
                current.classList.add('d-none');
            }
        }

        panel.addEventListener('show.bs.offcanvas', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;
            if (trigger.getAttribute('data-knowledge-mode') === 'edit') {
                openEdit(JSON.parse(trigger.getAttribute('data-document')));
                return;
            }
            openCreate();
        });

        @if ($errors->any())
            if (@json((string) old('document_id')) !== '') {
                heading.textContent = 'Edit document';
                form.action = updateBase.replace('00000000-0000-4000-8000-000000000000', @json((string) old('document_id')));
                method.disabled = false;
            }
            bootstrap.Offcanvas.getOrCreateInstance(panel).show();
        @endif
    })();
</script>
@endpush
@endif
