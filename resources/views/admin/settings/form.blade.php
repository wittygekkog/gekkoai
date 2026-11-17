@extends('admin.layouts.app')
@section('title', $setting->exists ? 'Edit setting' : 'New setting')
@section('crumb', 'Settings')
@section('content')
<div class="gk-card" style="max-width: 720px;">
    <h1 class="h4">{{ $setting->exists ? $setting->setting_name : 'New setting' }}</h1>
    <form method="post" action="{{ $setting->exists ? route('admin.settings.update', $setting) : route('admin.settings.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($setting->exists) @method('PATCH') @endif
        <label class="form-label" for="setting_slug">Slug</label>
        <input id="setting_slug" name="setting_slug" class="form-control mb-3" value="{{ old('setting_slug', $setting->setting_slug) }}" required>
        <label class="form-label" for="setting_name">Name</label>
        <input id="setting_name" name="setting_name" class="form-control mb-3" value="{{ old('setting_name', $setting->setting_name) }}" required>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="setting_group">Group</label>
                <select id="setting_group" name="setting_group" class="form-select">
                    @foreach (\App\Models\Setting::GROUPS as $key => $label)
                        <option value="{{ $key }}" @selected(old('setting_group', $setting->setting_group) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="input_type">Type</label>
                <select id="input_type" name="input_type" class="form-select">
                    @foreach (\App\Models\Setting::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('input_type', $setting->input_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="form-label mt-3" for="setting_value">Value</label>
        @if ($setting->isSecret())
            <input id="setting_value" name="setting_value" type="password" class="form-control mb-1" autocomplete="new-password">
            <div class="muted mb-3">Leave blank to keep the stored secret.</div>
        @else
            <textarea id="setting_value" name="setting_value" class="form-control mb-3">{{ old('setting_value', $setting->isSecret() ? '' : $setting->setting_value) }}</textarea>
        @endif
        <label class="form-label" for="setting_file">File (recommended {{ $setting->recommendedImageSize() }}). WebP, JPG, PNG, GIF, or ICO. Maximum 5 MB.</label>
        <input id="setting_file" name="setting_file" type="file" class="form-control mb-3" accept="image/webp,image/jpeg,image/png,image/gif,image/x-icon">
        <label class="form-label" for="help_text">Help text</label>
        <input id="help_text" name="help_text" class="form-control mb-3" value="{{ old('help_text', $setting->help_text) }}">
        <label class="form-label" for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" class="form-control mb-3" value="{{ old('sort_order', $setting->sort_order) }}">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <button class="btn btn-gk" type="submit">Save</button>
    </form>
    @if ($setting->exists)
        <form class="mt-3" method="post" action="{{ route('admin.settings.destroy', $setting) }}" onsubmit="return confirm('Delete this setting?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger" type="submit">Delete</button>
        </form>
    @endif
</div>
@endsection
