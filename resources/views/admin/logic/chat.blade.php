{{-- Shared add and edit chat. Voice uses the browser speech API and is not stored until Save. --}}
<div class="offcanvas offcanvas-end gk-drawer" tabindex="-1" id="logicChat" aria-labelledby="logicChatTitle">
    <div class="offcanvas-header">
        <div class="min-w-0">
            <h2 class="offcanvas-title h5 mb-0" id="logicChatTitle">Add a rule</h2>
            <p class="drawer-note">Type a sentence or use the microphone, then save.</p>
        </div>
        <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="gk-drawer-main">
            @if ($tenants->count() !== 1)
                <div>
                    <label class="form-label" for="logicTenantSelect">SBU</label>
                    <select id="logicTenantSelect" class="form-select">
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input id="logicTenantSelect" type="hidden" value="{{ $tenants->first()->id }}">
            @endif

            <div class="logic-thread" id="logicThread" aria-live="polite"></div>

            <form class="composer" id="logicComposer">
                <button class="mic-btn" id="logicMic" type="button" aria-pressed="false" aria-label="Speak a rule">
                    <i class="bi bi-mic" aria-hidden="true"></i>
                </button>
                <textarea id="logicMessage" class="form-control" rows="2" maxlength="4000" placeholder="Describe the rule..." aria-label="Rule message"></textarea>
                <button class="btn btn-gk" type="submit" aria-label="Send"><i class="bi bi-send"></i></button>
            </form>
            <div class="mic-bar d-none" id="logicMicBar" role="status">
                <span class="mic-dot" aria-hidden="true"></span>
                <span class="mic-note" id="logicMicNote">Listening. Pause when you finish the sentence.</span>
            </div>
        </div>

        <form class="gk-drawer-foot" id="logicSaveForm" method="post" action="{{ route('admin.logic.store') }}">
            @csrf
            <input type="hidden" name="_method" id="logicSaveMethod" value="POST" disabled>
            <input type="hidden" name="tenant_id" id="logicTenantField" value="{{ $tenants->count() === 1 ? $tenants->first()->id : '' }}">
            <input type="hidden" name="title" id="logicTitleField">
            <input type="hidden" name="body" id="logicBodyField">
            <button class="btn btn-gk w-100" id="logicSave" type="submit" disabled>Save rule</button>
        </form>
    </div>
</div>
