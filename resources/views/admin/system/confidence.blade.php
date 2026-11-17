@extends('admin.layouts.app')
@section('title', 'Confidence')
@section('crumb', 'Confidence Analytics')
@section('content')
<div class="page-head"><h1>Confidence analytics</h1><p>Scores stay capped until a labeled evaluation has at least 30 examples. The cap is not a probability.</p></div>
<div class="gk-card">
    <table class="table table-striped table-bordered align-middle gk-table mb-0">
        <thead><tr><th>Dataset</th><th class="num">Examples</th><th class="num">Intent accuracy</th><th class="fit">Passed</th><th class="fit">When</th></tr></thead>
        <tbody>
        @forelse ($runs as $run)
            <tr>
                <td>{{ $run->dataset_version }}</td>
                <td class="num">{{ $run->metrics['examples'] ?? 0 }}</td>
                <td class="num">{{ $run->metrics['intent_accuracy'] ?? '—' }}</td>
                <td class="fit">{{ $run->passed ? 'Yes' : 'No' }}</td>
                <td class="fit">{{ optional($run->evaluated_at)->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No evaluation runs. Automatic writes remain disabled.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
