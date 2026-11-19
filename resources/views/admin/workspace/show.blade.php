@extends('admin.layouts.app')
@section('title', $conversation->title)
@section('crumb', 'AI Workspace')
@section('content')
@php
    $params = $operation?->parameters ?? [];
    $missing = $operation?->missing_fields ?? [];
    $score = $operation?->confidence_score;
    $meta = $operation?->confidence_metadata ?? [];
    $fields = [
        'name' => 'Guest name',
        'email' => 'Email',
        'mobile' => 'Contact number',
        'arrival' => 'Arrival',
        'departure' => 'Departure',
        'guests' => 'Guests',
        'reference' => 'Reference',
    ];
@endphp
<div class="workspace">
    <aside class="gk-card conv-list">
        <form method="post" action="{{ route('admin.workspace.start') }}">
            @csrf
            <select name="tenant_id" class="form-select mb-2" aria-label="Select SBU" onchange="this.form.submit()">
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected($tenant->id === $conversation->tenant_id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        </form>
        <form method="post" action="{{ route('admin.workspace.start') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $conversation->tenant_id }}">
            <button class="btn btn-gk w-100" type="submit">New conversation</button>
        </form>
        @foreach ($conversations as $item)
            <a class="conv-item {{ $item->id === $conversation->id ? 'active' : '' }}" href="{{ route('admin.workspace.show', $item) }}">
                <strong>{{ $item->title }}</strong>
                <div class="muted">{{ $item->updated_at->diffForHumans() }}</div>
            </a>
        @endforeach
    </aside>
    <section class="gk-card">
        <div class="d-flex justify-content-between gap-2 flex-wrap">
            <div>
                <h1 class="h4 mb-1">{{ $conversation->title }}</h1>
                <div class="muted">{{ $conversation->tenant->name }} · {{ $conversation->model_name }}</div>
            </div>
            <span class="badge-soft badge-info">{{ $conversation->tenant->connector->mode ?? 'simulation' }}</span>
        </div>
        <div class="thread mt-3">
            @forelse ($conversation->messages as $message)
                <article class="bubble">
                    <div class="who">{{ $message->role === 'user' ? 'You' : 'AI' }}</div>
                    <div class="text w-100">
                        <div>{{ $message->content }}</div>
                        @if ($message->message_type === 'clarification' && $missing !== [])
                            <form class="draft-card" method="post" action="{{ route('admin.workspace.message', $conversation) }}">
                                @csrf
                                @foreach ($missing as $field)
                                    <label class="form-label mt-2" for="field-{{ $field }}">{{ $fields[$field] ?? $field }}</label>
                                    <input class="form-control" id="field-{{ $field }}" name="fields[{{ $field }}]" @if($field === 'guests') type="number" min="1" max="18" @endif>
                                @endforeach
                                <button class="btn btn-gk mt-3" type="submit">Send details</button>
                            </form>
                        @endif
                        @if ($message->message_type === 'approval_request')
                            @php
                                $approvalId = $message->structured_content['approval_id'] ?? null;
                                $approval = $approvalId
                                    ? $conversation->operations->flatMap(fn ($item) => $item->approvals)->firstWhere('id', $approvalId)
                                    : null;
                            @endphp
                            <div class="draft-card">
                                @foreach (($message->structured_content['parameters'] ?? []) as $key => $value)
                                    <div class="field-row"><span>{{ $fields[$key] ?? $key }}</span><strong>{{ is_scalar($value) ? $value : json_encode($value) }}</strong></div>
                                @endforeach
                                @if ($approval && $approval->status === 'pending')
                                    <form class="d-flex gap-2 mt-3" method="post" action="{{ route('admin.approvals.approve', $approvalId) }}">
                                        @csrf
                                        <input class="form-control" name="reason" placeholder="Approval note">
                                        <button class="btn btn-gk" type="submit">Approve and run</button>
                                    </form>
                                    <form class="d-flex gap-2 mt-2" method="post" action="{{ route('admin.approvals.reject', $approvalId) }}">
                                        @csrf
                                        <input class="form-control" name="reason" placeholder="Reason for rejection" required>
                                        <button class="btn btn-outline-danger" type="submit">Reject</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="muted">Ask GekkoAi to check availability or prepare a booking. Writes wait for approval.</p>
            @endforelse
        </div>
        @if (session('transcript_id'))
            <form class="draft-card" method="post" action="{{ route('admin.workspace.transcript', $conversation) }}">
                @csrf
                <input type="hidden" name="transcription_id" value="{{ session('transcript_id') }}">
                <label class="form-label" for="corrected_transcript">Confirmed transcript</label>
                <textarea id="corrected_transcript" name="corrected_transcript" class="form-control" required>{{ old('corrected_transcript', $pendingTranscript?->transcript) }}</textarea>
                <button class="btn btn-gk mt-2" type="submit">Use this transcript</button>
            </form>
        @endif
        <form class="composer" id="messageForm" method="post" action="{{ route('admin.workspace.message', $conversation) }}">
            @csrf
            <button class="mic-btn" id="micBtn" type="button" aria-pressed="false" aria-label="Start voice instruction">
                <i class="bi bi-mic" aria-hidden="true"></i>
            </button>
            <textarea name="body" class="form-control" placeholder="Ask GekkoAi to do something..." aria-label="Instruction"></textarea>
            <button class="btn btn-gk" type="submit" aria-label="Send"><i class="bi bi-send"></i></button>
            <button class="mic-btn" id="audioUploadBtn" type="button" aria-label="Upload an audio file"><i class="bi bi-paperclip" aria-hidden="true"></i></button>
        </form>
        <div class="mic-bar d-none" id="micBar" role="status" aria-live="polite">
            <span class="mic-dot" aria-hidden="true"></span>
            <span class="mic-timer" id="micTimer">00:00</span>
            <span class="mic-note" id="micNote">Press the microphone again to send the recording for transcription. Confirm the words before they become an instruction.</span>
            <button class="btn btn-outline-secondary btn-sm ms-auto" id="micCancel" type="button">Cancel</button>
        </div>
        <form id="audioForm" method="post" action="{{ route('admin.workspace.audio', $conversation) }}" enctype="multipart/form-data">
            @csrf
            <input id="audioInput" class="visually-hidden" type="file" name="audio" accept="audio/webm,audio/wav,audio/x-wav,audio/mpeg,audio/ogg,audio/mp4,video/webm" tabindex="-1">
        </form>
    </section>
    <aside class="gk-card inspector">
        <h2 class="h6">Operation inspector</h2>
        <div class="gate-card">
            <div class="muted">Understanding score</div>
            <strong>{{ $score !== null ? number_format($score * 100, 0).'%' : '—' }}</strong>
            <div class="muted">{{ ($meta['calibrated'] ?? false) ? 'Calibrated' : 'Uncalibrated cap — not a probability' }}</div>
        </div>
        <div class="gate-card">
            <div class="muted">Intent</div>
            <strong>{{ $operation?->intent ?: 'Waiting' }}</strong>
        </div>
        <div class="gate-card">
            <div class="muted">Required fields</div>
            @foreach ($fields as $key => $label)
                @if (array_key_exists($key, $params) || in_array($key, $missing, true))
                    <div class="field-row">
                        <span>{{ $label }}</span>
                        @if (in_array($key, $missing, true) || ! isset($params[$key]) || $params[$key] === '')
                            <span class="missing">Missing</span>
                        @else
                            <span class="ok">{{ $params[$key] }}</span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
        <div class="gate-card">
            <div class="muted">Execution gate</div>
            <strong>{{ $operation ? str_replace('_', ' ', $operation->status) : 'No request yet' }}</strong>
            <div class="muted">Risk {{ $operation?->risk_level ?: '—' }}. High-risk and uncalibrated writes stay behind approval.</div>
        </div>
        @if ($operation)
            <form method="post" action="{{ route('admin.operations.reconcile', $operation) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Reconcile</button></form>
        @endif
    </aside>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var micBtn = document.getElementById('micBtn');
    var uploadBtn = document.getElementById('audioUploadBtn');
    var cancelBtn = document.getElementById('micCancel');
    var bar = document.getElementById('micBar');
    var timerEl = document.getElementById('micTimer');
    var noteEl = document.getElementById('micNote');
    var fileInput = document.getElementById('audioInput');
    var audioForm = document.getElementById('audioForm');
    var messageForm = document.getElementById('messageForm');
    if (!micBtn || !fileInput || !audioForm) { return; }

    var maxSeconds = {{ (int) config('gekko.audio_max_seconds') }};
    var maxBytes = {{ (int) config('gekko.audio_max_kilobytes') }} * 1024;
    var recorder = null;
    var stream = null;
    var chunks = [];
    var startedAt = 0;
    var ticker = null;
    var state = 'idle';
    var mimeType = '';

    function supported() {
        return !!(navigator.mediaDevices && window.MediaRecorder && window.isSecureContext);
    }

    function pickMime() {
        var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
        for (var i = 0; i < types.length; i++) {
            if (MediaRecorder.isTypeSupported(types[i])) { return types[i]; }
        }
        return '';
    }

    function extension(type) {
        if (type.indexOf('ogg') !== -1) { return 'ogg'; }
        if (type.indexOf('mp4') !== -1) { return 'm4a'; }
        if (type.indexOf('wav') !== -1) { return 'wav'; }
        return 'webm';
    }

    function formatTime(seconds) {
        var whole = Math.max(0, Math.floor(seconds));
        var minutes = String(Math.floor(whole / 60)).padStart(2, '0');
        var remain = String(whole % 60).padStart(2, '0');
        return minutes + ':' + remain;
    }

    function showBar(recording) {
        bar.classList.remove('d-none');
        bar.classList.toggle('is-recording', recording);
    }

    function setNote(text) {
        noteEl.textContent = text;
    }

    function releaseStream() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
    }

    function resetIdle() {
        if (ticker) { clearInterval(ticker); ticker = null; }
        recorder = null;
        chunks = [];
        state = 'idle';
        micBtn.setAttribute('aria-pressed', 'false');
        micBtn.setAttribute('aria-label', 'Start voice instruction');
        micBtn.disabled = false;
        timerEl.textContent = '00:00';
        bar.classList.add('d-none');
        bar.classList.remove('is-recording');
        releaseStream();
    }

    function fail(message) {
        resetIdle();
        showBar(false);
        setNote(message);
        cancelBtn.textContent = 'Dismiss';
    }

    function submitBlob(blob) {
        if (!blob || blob.size < 100) {
            fail('That recording was too short. Press the microphone and speak, then press it again.');
            return;
        }
        if (blob.size > maxBytes) {
            fail('The recording is larger than the allowed ' + Math.round(maxBytes / 1024) + ' KB. Record a shorter instruction.');
            return;
        }
        if (typeof DataTransfer === 'undefined') {
            fail('This browser cannot attach a recording. Use the upload button instead.');
            return;
        }
        var type = blob.type || mimeType || 'audio/webm';
        var file = new File([blob], 'instruction.' + extension(type), { type: type });
        var transfer = new DataTransfer();
        transfer.items.add(file);
        fileInput.files = transfer.files;
        state = 'uploading';
        micBtn.disabled = true;
        setNote('Sending the recording…');
        audioForm.submit();
    }

    function stopAndSend() {
        if (!recorder || recorder.state === 'inactive') { return; }
        state = 'stopping';
        micBtn.disabled = true;
        recorder.onstop = function () {
            var blob = new Blob(chunks, { type: mimeType || recorder.mimeType || 'audio/webm' });
            releaseStream();
            submitBlob(blob);
        };
        recorder.stop();
    }

    function startRecording() {
        if (!supported()) {
            fileInput.click();
            return;
        }
        mimeType = pickMime();
        navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }
        }).then(function (userStream) {
            stream = userStream;
            chunks = [];
            try {
                recorder = mimeType
                    ? new MediaRecorder(stream, { mimeType: mimeType, audioBitsPerSecond: 32000 })
                    : new MediaRecorder(stream);
            } catch (error) {
                recorder = new MediaRecorder(stream);
            }
            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) { chunks.push(event.data); }
            };
            recorder.start(250);
            startedAt = Date.now();
            state = 'recording';
            micBtn.setAttribute('aria-pressed', 'true');
            micBtn.setAttribute('aria-label', 'Stop and send voice instruction');
            showBar(true);
            setNote('Recording. Press the microphone again to send. Cancel discards it.');
            cancelBtn.textContent = 'Cancel';
            ticker = setInterval(function () {
                var elapsed = (Date.now() - startedAt) / 1000;
                timerEl.textContent = formatTime(elapsed);
                if (elapsed >= maxSeconds) { stopAndSend(); }
            }, 250);
        }).catch(function (error) {
            var message = 'The microphone could not be started. Upload an audio file instead.';
            if (error && error.name === 'NotAllowedError') {
                message = 'Microphone access was blocked. Allow it for this site, or upload an audio file.';
            } else if (error && error.name === 'NotFoundError') {
                message = 'No microphone was found. Upload an audio file instead.';
            } else if (error && error.name === 'NotReadableError') {
                message = 'The microphone is in use by another application.';
            } else if (error && error.name === 'SecurityError') {
                message = 'Voice recording needs localhost or HTTPS.';
            }
            fail(message);
        });
    }

    micBtn.addEventListener('click', function () {
        if (state === 'recording') { stopAndSend(); return; }
        if (state === 'idle') { startRecording(); }
    });
    cancelBtn.addEventListener('click', function () {
        if (recorder && recorder.state === 'recording') {
            recorder.onstop = function () { resetIdle(); };
            recorder.stop();
            return;
        }
        resetIdle();
    });
    uploadBtn.addEventListener('click', function () {
        if (state === 'recording') { return; }
        fileInput.click();
    });
    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) { return; }
        if (file.size > maxBytes) {
            fileInput.value = '';
            fail('That file is larger than ' + Math.round(maxBytes / 1024) + ' KB.');
            return;
        }
        state = 'uploading';
        micBtn.disabled = true;
        showBar(false);
        audioForm.submit();
    });
    messageForm.addEventListener('submit', function () {
        if (state === 'recording' && recorder) {
            recorder.onstop = function () { releaseStream(); };
            recorder.stop();
            state = 'idle';
        }
    });
    window.addEventListener('pagehide', releaseStream);
    window.addEventListener('beforeunload', function (event) {
        if (state === 'recording') {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    if (!supported()) {
        micBtn.setAttribute('aria-label', 'Upload a voice recording');
        micBtn.querySelector('i').className = 'bi bi-mic-mute';
    }
})();
</script>
@endpush
