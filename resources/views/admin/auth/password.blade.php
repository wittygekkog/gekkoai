@extends('admin.layouts.app')
@section('title', 'Change password')
@section('crumb', 'Password')
@section('content')
<div class="gk-card" style="max-width: 520px;">
    <h1 class="h4">Choose a new password</h1>
    <p class="muted">Bootstrap passwords must be replaced before the workspace opens. Use at least 6 characters.</p>
    <form method="post" action="{{ route('admin.password.update') }}">
        @csrf
        <label class="form-label" for="password">New password</label>
        <input id="password" name="password" type="password" class="form-control mb-3" required minlength="6">
        <label class="form-label" for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-3" required minlength="6">
        @error('password')<div class="text-danger mb-2">{{ $message }}</div>@enderror
        <button class="btn btn-gk" type="submit">Save password</button>
    </form>
</div>
@endsection
