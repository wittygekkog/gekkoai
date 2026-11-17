@extends('admin.layouts.app')
@section('title', 'AI models')
@section('crumb', 'AI Models')
@section('content')
<div class="page-head"><h1>AI models</h1><p>Stored providers stay inactive until an evaluation passes. The workspace currently uses the deterministic analyzer.</p></div>
<div class="gk-two">
    <section class="gk-card">
        <table class="table table-striped table-bordered align-middle gk-table mb-0">
            <thead><tr><th>Provider</th><th>Model</th><th class="fit">Purpose</th><th class="fit">Status</th></tr></thead>
            <tbody>
            @forelse ($models as $model)
                <tr>
                    <td>{{ $model->provider }}</td>
                    <td>{{ $model->model_name }}</td>
                    <td class="fit">{{ $model->purpose }}</td>
                    <td class="fit">{{ $model->deployment_status }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No model configuration is stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    @if (auth('admin')->user()->isSuperAdmin())
        <section class="gk-card">
            <h2 class="block-title">Add a provider</h2>
            <form method="post" action="{{ route('admin.models.store') }}">
                @csrf
                <label class="form-label" for="provider">Provider</label>
                <input id="provider" name="provider" class="form-control mb-3" required>
                <label class="form-label" for="model_name">Model name</label>
                <input id="model_name" name="model_name" class="form-control mb-3" required>
                <label class="form-label" for="purpose">Purpose</label>
                <select id="purpose" name="purpose" class="form-select mb-3">
                    @foreach (['intent', 'extraction', 'conversation', 'retrieval', 'planning', 'transcription'] as $purpose)
                        <option value="{{ $purpose }}">{{ $purpose }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="base_url">Base URL</label>
                <input id="base_url" name="base_url" type="url" class="form-control mb-3">
                <label class="form-label" for="api_key">API key</label>
                <input id="api_key" name="api_key" type="password" class="form-control mb-3" autocomplete="new-password">
                <button class="btn btn-gk" type="submit">Store inactive</button>
            </form>
        </section>
    @endif
</div>
@endsection
