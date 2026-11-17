@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Dashboard')
@section('content')
<div class="page-head">
    <h1>Operations overview</h1>
    <p>Real-time performance and AI operations across your SBUs.</p>
</div>
<div class="gk-grid kpi-grid">
    <article class="gk-card kpi"><div class="kpi-icon"><i class="bi bi-clipboard-data"></i></div><div><span>Requests today</span><b>{{ $counts['today'] }}</b></div></article>
    <article class="gk-card kpi"><div class="kpi-icon"><i class="bi bi-lightning-charge"></i></div><div><span>Automated</span><b>{{ $counts['automated'] }}</b></div></article>
    <article class="gk-card kpi"><div class="kpi-icon"><i class="bi bi-person-check"></i></div><div><span>Pending approvals</span><b>{{ $counts['approvals'] }}</b></div></article>
    <article class="gk-card kpi"><div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div><div><span>Flags</span><b>{{ $counts['escalations'] }}</b></div></article>
</div>
<div class="gk-grid dash-grid mt-3">
    <section class="gk-card">
        <h2 class="block-title">Weekly request volume</h2>
        <p class="muted">Recorded operation requests over the last 7 days.</p>
        <canvas id="volume" height="140"></canvas>
    </section>
    <section class="gk-card">
        <h2 class="block-title">Execution outcomes</h2>
        <p class="muted">All recorded operations.</p>
        <ul class="list-unstyled mb-0">
            <li class="field-row"><span>Successful</span><b class="num">{{ $counts['successful'] }}</b></li>
            <li class="field-row"><span>Needs review</span><b class="num">{{ $counts['review'] }}</b></li>
            <li class="field-row"><span>Failed or unknown</span><b class="num">{{ $counts['failed'] }}</b></li>
            <li class="field-row"><span>Cancelled</span><b class="num">{{ $counts['cancelled'] }}</b></li>
        </ul>
    </section>
</div>
<div class="gk-grid dash-grid mt-3">
    <section class="gk-card">
        <h2 class="block-title">Recent AI operations</h2>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle gk-table mb-0">
                <thead><tr><th>SBU</th><th>Intent</th><th class="num">Confidence</th><th class="fit">Status</th><th class="fit">Date</th></tr></thead>
                <tbody>
                @forelse ($recent as $row)
                    <tr>
                        <td>{{ $row->tenant->name }}</td>
                        <td>{{ $row->intent ?: '—' }}</td>
                        <td class="num">{{ $row->confidence_score !== null ? number_format($row->confidence_score * 100, 0).'%' : '—' }}</td>
                        <td class="fit"><span class="badge-soft badge-info">{{ str_replace('_', ' ', $row->status) }}</span></td>
                        <td class="fit">{{ $row->created_at->timezone($row->tenant->timezone)->format('M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No operations yet. Open the AI workspace to send the first instruction.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="gk-card">
        <h2 class="block-title">Connector health</h2>
        @foreach ($connectors as $tenant)
            <div class="field-row">
                <span><i class="bi bi-plug"></i> {{ $tenant->name }}</span>
                <span class="badge-soft {{ $tenant->connector?->mode === 'simulation' ? 'badge-wait' : 'badge-ok' }}">{{ $tenant->connector->status ?? 'missing' }}</span>
            </div>
        @endforeach
    </section>
</div>
@endsection
@push('scripts')
<script>
    const days = @json($days);
    const canvas = document.getElementById('volume');
    const ctx = canvas.getContext('2d');
    const max = Math.max(1, ...days.map(d => d.count));
    function draw() {
        const width = canvas.width = canvas.clientWidth * devicePixelRatio;
        const height = canvas.height = 160 * devicePixelRatio;
        ctx.clearRect(0, 0, width, height);
        ctx.strokeStyle = '#7c3aed';
        ctx.lineWidth = 2 * devicePixelRatio;
        ctx.beginPath();
        days.forEach((day, index) => {
            const x = (index / Math.max(1, days.length - 1)) * (width - 40) + 20;
            const y = height - 30 - (day.count / max) * (height - 50);
            index === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();
        ctx.fillStyle = '#667085';
        ctx.font = (12 * devicePixelRatio) + 'px Segoe UI, sans-serif';
        days.forEach((day, index) => {
            const x = (index / Math.max(1, days.length - 1)) * (width - 40) + 20;
            ctx.fillText(day.label, x - 12, height - 8);
        });
    }
    draw();
    window.addEventListener('resize', draw);
</script>
@endpush
