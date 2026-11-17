@extends('admin.layouts.app')
@section('title', 'Data')
@section('crumb', 'Data')
@section('content')
<div class="page-head"><h1>Data</h1><p>Only approved documents can be retrieved. They cannot grant tools or permissions.</p></div>
<div class="gk-two">
    <section class="gk-card">
        <table class="table table-striped table-bordered align-middle gk-table mb-0">
            <thead><tr><th>Title</th><th>SBU</th><th class="fit">Status</th><th class="fit"></th></tr></thead>
            <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->tenant->name }}</td>
                    <td class="fit">{{ $document->approval_status }}</td>
                    <td class="fit">
                        @if ($document->approval_status !== 'approved')
                            <form method="post" action="{{ route('admin.knowledge.approve', $document) }}">@csrf<button class="btn btn-sm btn-gk" type="submit">Approve</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No documents yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $documents->links('pagination::bootstrap-5') }}</div>
    </section>
    <section class="gk-card">
        <h2 class="block-title">Add a document</h2>
        <form method="post" action="{{ route('admin.knowledge.store') }}">
            @csrf
            <label class="form-label" for="tenant_id">SBU</label>
            <select id="tenant_id" name="tenant_id" class="form-select mb-3" required>
                @php
                    $admin = auth('admin')->user();
                    $knowledgeTenants = ($admin->isSuperAdmin() || $admin->access_scope === 'platform')
                        ? \App\Models\Tenant::query()->orderBy('name')->get()
                        : $admin->tenants()->wherePivot('status', 'active')->orderBy('name')->get();
                @endphp
                @foreach ($knowledgeTenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                @endforeach
            </select>
            <label class="form-label" for="title">Title</label>
            <input id="title" name="title" class="form-control mb-3" required>
            <label class="form-label" for="content">Text</label>
            <textarea id="content" name="content" class="form-control mb-3" required></textarea>
            <button class="btn btn-gk" type="submit">Store for review</button>
        </form>
    </section>
</div>
@endsection
