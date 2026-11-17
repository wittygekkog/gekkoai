@extends('admin.layouts.app')
@section('title', $tenant->exists ? 'Edit SBU' : 'New SBU')
@section('crumb', 'SBUs')
@section('content')
<div class="gk-card" style="max-width: 760px;">
    <h1 class="h4">{{ $tenant->exists ? 'Edit '.$tenant->name : 'New business unit' }}</h1>
    <form method="post" action="{{ $tenant->exists ? route('admin.tenants.update', $tenant) : route('admin.tenants.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($tenant->exists) @method('PATCH') @endif
        <label class="form-label" for="name">Name</label>
        <input id="name" name="name" class="form-control mb-3" value="{{ old('name', $tenant->name) }}" required>
        <label class="form-label" for="slug">Slug</label>
        <input id="slug" name="slug" class="form-control mb-3" value="{{ old('slug', $tenant->slug) }}">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" class="form-control mb-3">{{ old('description', $tenant->description) }}</textarea>
        <label class="form-label" for="website_url">Website</label>
        <input id="website_url" name="website_url" type="url" class="form-control mb-3" value="{{ old('website_url', $tenant->website_url) }}">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="timezone">Timezone</label>
                <input id="timezone" name="timezone" class="form-control" value="{{ old('timezone', $tenant->timezone) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="locale">Locale</label>
                <input id="locale" name="locale" class="form-control" value="{{ old('locale', $tenant->locale) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $tenant->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="form-label mt-3" for="logo">Logo (recommended 600 × 400 px, 3:2). WebP, JPG, or PNG. Maximum 5 MB. Shown on the SBU list.</label>
        <input id="logo" name="logo" type="file" class="form-control mb-3" accept="image/webp,image/jpeg,image/png">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <button class="btn btn-gk" type="submit">Save</button>
    </form>
</div>
@endsection
