<script>
(function () {
    var panel = document.getElementById('objectiveChat');
    var thread = document.getElementById('objectiveThread');
    var composer = document.getElementById('objectiveComposer');
    var message = document.getElementById('objectiveMessage');
    var mic = document.getElementById('objectiveMic');
    var micBar = document.getElementById('objectiveMicBar');
    var micNote = document.getElementById('objectiveMicNote');
    var tenantSelect = document.getElementById('objectiveTenantSelect');
    var saveForm = document.getElementById('objectiveSaveForm');
    var saveMethod = document.getElementById('objectiveSaveMethod');
    var tenantField = document.getElementById('objectiveTenantField');
    var titleField = document.getElementById('objectiveTitleField');
    var bodyField = document.getElementById('objectiveBodyField');
    var saveButton = document.getElementById('objectiveSave');
    var titleEl = document.getElementById('objectiveChatTitle');
    if (!panel || !thread || !composer) { return; }

    var storeUrl = @json(route('admin.objective.store'));
    var chatUrl = @json(route('admin.objective.chat'));
    var updateUrl = @json(route('admin.objective.update', ['objective' => '00000000-0000-4000-8000-000000000000']));
    var token = @json(csrf_token());
    var draft = { id: '', title: '', body: '' };
    var listening = false;
    var recognition = null;
    var Speech = window.SpeechRecognition || window.webkitSpeechRecognition;

    function bubble(role, text, extra) {
        var item = document.createElement('article');
        item.className = 'logic-bubble ' + role;
        var paragraph = document.createElement('p');
        paragraph.textContent = text;
        item.appendChild(paragraph);
        if (extra && extra.title) {
            var card = document.createElement('div');
            card.className = 'logic-draft';
            card.innerHTML = '<div><span>Title</span><strong></strong></div><div><span>Objective</span><strong></strong></div>';
            card.querySelectorAll('strong')[0].textContent = extra.title;
            card.querySelectorAll('strong')[1].textContent = extra.body || '';
            item.appendChild(card);
        }
        thread.appendChild(item);
        thread.scrollTop = thread.scrollHeight;
    }

    function setDraft(next) {
        draft.title = next.title || '';
        draft.body = next.body || '';
        titleField.value = draft.title;
        bodyField.value = draft.body;
        saveButton.disabled = draft.title === '' || draft.body === '';
    }

    function openCreate() {
        draft = { id: '', title: '', body: '' };
        titleEl.textContent = 'Add an objective';
        saveForm.action = storeUrl;
        saveMethod.disabled = true;
        if (tenantSelect && tenantSelect.tagName === 'SELECT') { tenantSelect.disabled = false; }
        tenantField.value = tenantSelect ? tenantSelect.value : '';
        titleField.value = '';
        bodyField.value = '';
        saveButton.disabled = true;
        thread.replaceChildren();
        bubble('assistant', 'Tell me the outcome in a sentence. You can type it or use the microphone.');
    }

    function openEdit(objective) {
        draft = { id: objective.id, title: objective.title, body: objective.body };
        titleEl.textContent = 'Edit objective';
        saveForm.action = updateUrl.replace('00000000-0000-4000-8000-000000000000', objective.id);
        saveMethod.disabled = false;
        saveMethod.value = 'PATCH';
        if (tenantSelect && tenantSelect.tagName === 'SELECT') {
            tenantSelect.value = objective.tenant_id;
            tenantSelect.disabled = true;
        }
        tenantField.value = objective.tenant_id;
        setDraft(objective);
        thread.replaceChildren();
        bubble('assistant', 'This is the current objective. Say what should change, or ask me to improve it.', objective);
    }

    function stopVoice() {
        listening = false;
        if (recognition) {
            recognition.onend = null;
            try { recognition.stop(); } catch (error) { /* already stopped */ }
        }
        mic.setAttribute('aria-pressed', 'false');
        mic.setAttribute('aria-label', 'Speak an objective');
        micBar.classList.remove('is-recording');
        micBar.classList.add('d-none');
    }

    function startVoice() {
        if (!Speech) {
            micBar.classList.remove('d-none');
            micNote.textContent = 'This browser cannot type from the microphone. Type the objective instead.';
            message.focus();
            return;
        }
        recognition = new Speech();
        recognition.lang = 'en-PH';
        recognition.interimResults = true;
        recognition.continuous = false;
        listening = true;
        mic.setAttribute('aria-pressed', 'true');
        mic.setAttribute('aria-label', 'Stop voice');
        micBar.classList.remove('d-none');
        micBar.classList.add('is-recording');
        micNote.textContent = 'Listening. Pause when you finish the sentence.';
        recognition.onresult = function (event) {
            var said = '';
            var finalText = '';
            for (var i = 0; i < event.results.length; i++) {
                said += event.results[i][0].transcript;
                if (event.results[i].isFinal) { finalText += event.results[i][0].transcript; }
            }
            message.value = said.trim();
            if (finalText.trim() !== '') {
                stopVoice();
                send(finalText.trim());
            }
        };
        recognition.onerror = function () {
            stopVoice();
            micBar.classList.remove('d-none');
            micNote.textContent = 'The microphone could not be used. Type the objective instead.';
        };
        recognition.onend = function () {
            if (listening) { stopVoice(); }
        };
        recognition.start();
    }

    function send(text) {
        var tenantId = tenantSelect ? tenantSelect.value : tenantField.value;
        if (!tenantId || text === '') { return; }
        tenantField.value = tenantId;
        bubble('user', text);
        message.value = '';
        saveButton.disabled = true;
        fetch(chatUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                tenant_id: tenantId,
                objective_id: draft.id || null,
                title: draft.title || null,
                body: draft.body || null,
                message: text
            })
        }).then(function (response) {
            return response.json().then(function (payload) {
                return { ok: response.ok, payload: payload };
            });
        }).then(function (result) {
            if (!result.ok) {
                bubble('assistant', (result.payload && result.payload.message) || 'The objective could not be prepared.');
                return;
            }
            setDraft(result.payload.data);
            bubble('assistant', result.payload.message, result.payload.data);
        }).catch(function () {
            bubble('assistant', 'The objective could not be prepared. Try again.');
        });
    }

    panel.addEventListener('show.bs.offcanvas', function (event) {
        stopVoice();
        var trigger = event.relatedTarget;
        var mode = trigger ? trigger.getAttribute('data-objective-mode') : 'create';
        if (mode === 'edit' && trigger) {
            openEdit(JSON.parse(trigger.getAttribute('data-objective')));
            return;
        }
        openCreate();
    });
    panel.addEventListener('hide.bs.offcanvas', stopVoice);

    composer.addEventListener('submit', function (event) {
        event.preventDefault();
        send(message.value.trim());
    });
    mic.addEventListener('click', function () {
        if (listening) { stopVoice(); return; }
        startVoice();
    });
    if (tenantSelect && tenantSelect.tagName === 'SELECT') {
        tenantSelect.addEventListener('change', function () {
            if (!draft.id) { tenantField.value = tenantSelect.value; }
        });
    }
})();
</script>
