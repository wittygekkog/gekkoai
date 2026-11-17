@extends('admin.layouts.app')
@section('title', 'GitHub deploy')
@section('crumb', 'GitHub Deploy')
@section('content')
<div class="gk-card">
    <h1 class="h4 mb-2">GitHub deploy</h1>
    <p class="mb-3">A push webhook at <code>POST /github/deploy</code> fetches <code>main</code> and resets this application to <code>FETCH_HEAD</code>. The screen does not run git.</p>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle gk-table mb-3">
            <tbody>
                <tr>
                    <th class="fit">Endpoint</th>
                    <td><code>POST /github/deploy</code></td>
                </tr>
                <tr>
                    <th class="fit">Signature</th>
                    <td><code>X-Hub-Signature-256</code> checked against <code>GITHUB_WEBHOOK_SECRET</code></td>
                </tr>
                <tr>
                    <th class="fit">Branch</th>
                    <td><code>refs/heads/main</code> only. Other branches are ignored.</td>
                </tr>
                <tr>
                    <th class="fit">Repository</th>
                    <td><code>{{ $owner }}/{{ $repo }}</code></td>
                </tr>
                <tr>
                    <th class="fit">Commands</th>
                    <td><code>git fetch &lt;token-url&gt; main</code>, then <code>git reset --hard FETCH_HEAD</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <span class="badge-soft {{ $secretConfigured ? 'badge-ok' : 'badge-wait' }}">{{ $secretConfigured ? 'Webhook secret is set' : 'Webhook secret is empty' }}</span>
        <span class="badge-soft {{ $tokenConfigured ? 'badge-ok' : 'badge-wait' }}">{{ $tokenConfigured ? 'Deploy token is set' : 'Deploy token is empty' }}</span>
    </div>
</div>
@endsection
