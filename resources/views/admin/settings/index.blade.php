@extends('admin.layouts.app')
@section('title', 'Settings')
@section('crumb', 'Settings')
@section('content')
<div class="page-head d-flex justify-content-between align-items-end gap-3 flex-wrap">
    <div><h1>Settings</h1><p>Platform configuration. Secrets stay encrypted and are never shown back in full.</p></div>
    <a class="btn btn-gk" href="{{ route('admin.settings.create') }}">Add setting</a>
</div>
<form class="search-form d-flex gap-2 mb-3 flex-wrap" method="get">
    <input class="form-control" style="max-width: 280px;" name="q" value="{{ $q }}" placeholder="Search" aria-label="Search settings">
    <select name="group" class="form-select" style="max-width: 220px;" aria-label="Group">
        <option value="">All groups</option>
        @foreach (\App\Models\Setting::GROUPS as $key => $label)
            <option value="{{ $key }}" @selected($group === $key)>{{ $label }}</option>
        @endforeach
    </select>
</form>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Name</th><th class="fit">Group</th><th>Value</th><th class="fit"></th></tr></thead>
        <tbody>
        @forelse ($items as $setting)
            <tr>
                <td>{{ $setting->setting_name }}<div class="muted">{{ $setting->setting_slug }}</div></td>
                <td class="fit">{{ $setting->groupLabel() }}</td>
                <td>{{ $setting->isSecret() ? 'Stored' : \Illuminate\Support\Str::limit((string) $setting->setting_value, 80) }}</td>
                <td class="fit"><a href="{{ route('admin.settings.edit', $setting) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="4">No settings match this filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $items->links('pagination::bootstrap-5') }}</div>
@endsection
