@extends('admin.layouts.app')
@section('title', 'GitHub deploy')
@section('crumb', 'GitHub Deploy')
@section('content')
<div class="gk-card" style="max-width: 720px;">
    <h1 class="h4">GitHub deploy</h1>
    <p>The webhook is <code>POST /github/deploy</code>. It checks <code>X-Hub-Signature-256</code> against <code>GITHUB_WEBHOOK_SECRET</code>.</p>
    <p>An empty secret rejects every delivery. A valid signature while deployment is disabled returns HTTP 423.</p>
    <p class="mb-0">This screen does not run git commands. Resetting the working tree from a webhook is not available.</p>
    <div class="mt-3 badge-soft {{ $enabled ? 'badge-wait' : 'badge-info' }}">{{ $enabled ? 'GITHUB_DEPLOY_ENABLED is on. Execution still refuses a git reset.' : 'Deployment execution is off.' }}</div>
</div>
@endsection
