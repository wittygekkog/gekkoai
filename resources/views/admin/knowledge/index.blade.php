@extends('admin.layouts.app')
@section('title', 'Data')
@section('crumb', 'Data')
@section('content')
@php($columns = $selectedTenant ? 4 : 5)
<div class="page-head d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div>
        <h1>Data</h1>
        @if ($selectedTenant)
            <p>Documents for {{ $selectedTenant->name }}. Only approved documents can be retrieved, and they cannot grant tools.</p>
        @else
            <p>Select an SBU in the workspace menu to add a document. This list shows every SBU you can access.</p>
        @endif
    </div>
    @if ($canManage)
        <button class="btn btn-gk" type="button" data-bs-toggle="offcanvas" data-bs-target="#knowledgeFormCanvas" data-knowledge-mode="create">Add a document</button>
    @endif
</div>

<section class="gk-card">
    <h2 class="block-title">Documents</h2>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle gk-table mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    @unless ($selectedTenant)
                        <th>SBU</th>
                    @endunless
                    <th class="fit">File</th>
                    <th class="fit">Status</th>
                    <th class="fit"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td>{{ $document->title }}</td>
                    @unless ($selectedTenant)
                        <td>{{ $document->tenant->name }}</td>
                    @endunless
                    <td class="fit">
                        @if (is_string($document->source_reference) && str_starts_with($document->source_reference, 'knowledge/'))
                            <a href="{{ route('admin.knowledge.file', $document) }}">Download</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="fit">{{ $document->approval_status }}</td>
                    <td class="fit">
                        @if ($manageableTenantIds->contains($document->tenant_id))
                            <div class="gk-row-actions">
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#knowledgeFormCanvas" data-knowledge-mode="edit" data-document="{{ json_encode(['id' => $document->id, 'title' => $document->title, 'content' => $document->content, 'tenant' => $document->tenant->name, 'file' => (is_string($document->source_reference) && str_starts_with($document->source_reference, 'knowledge/')) ? route('admin.knowledge.file', $document) : null], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">Edit</button>
                                @if ($document->approval_status !== 'approved')
                                    <form method="post" action="{{ route('admin.knowledge.approve', $document) }}">@csrf<button class="btn btn-gk" type="submit">Approve</button></form>
                                @endif
                                <form method="post" action="{{ route('admin.knowledge.destroy', $document) }}" onsubmit="return confirm('Delete this document?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $columns }}">No documents yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $documents->links('pagination::bootstrap-5') }}</div>
</section>

@if ($manageableTenantIds->isNotEmpty())
    <div class="offcanvas offcanvas-end data-offcanvas" tabindex="-1" id="knowledgeFormCanvas" aria-labelledby="knowledgeFormTitle">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h5 mb-0" id="knowledgeFormTitle">Add a document</h2>
            <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            @if ($errors->any())
                <div class="alert alert-danger border mb-0">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            <p class="form-text mb-0" id="knowledgeScope">{{ $selectedTenant ? 'Saved for '.$selectedTenant->name.'.' : '' }}</p>
            <form id="knowledgeForm" method="post" action="{{ route('admin.knowledge.store') }}" enctype="multipart/form-data">
                @csrf
                <input id="knowledgeMethod" type="hidden" name="_method" value="PATCH" @disabled(! $errors->any() || ! old('document_id'))>
                <input id="knowledgeDocumentId" type="hidden" name="document_id" value="{{ $errors->any() ? old('document_id') : '' }}">
                <label class="form-label" for="knowledgeTitle">Title</label>
                <input id="knowledgeTitle" name="title" class="form-control" value="{{ $errors->any() ? old('title') : '' }}" required maxlength="180">
                <label class="form-label mt-3" for="knowledgeContent">Text</label>
                <textarea id="knowledgeContent" name="content" class="form-control" rows="8" maxlength="50000">{{ $errors->any() ? old('content') : '' }}</textarea>
                <label class="form-label mt-3" for="knowledgeFile">File</label>
                <input id="knowledgeFile" name="file" type="file" class="form-control" accept=".txt,.md,.csv,.json,.doc,.docx,.pdf,text/plain,text/csv,application/json,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <div class="form-text">TXT, MD, CSV, JSON, DOC, DOCX, or PDF. Maximum 10 MB. A text file can fill the document when the text box is empty.</div>
                <p class="form-text d-none" id="knowledgeCurrentFile"></p>
                <button class="btn btn-gk mt-3" type="submit">Save document</button>
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
