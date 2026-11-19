{{-- Shared add and edit chat. Voice uses the browser speech API and is not stored until Save. --}}
<div class="offcanvas offcanvas-end gk-drawer" tabindex="-1" id="objectiveChat" aria-labelledby="objectiveChatTitle">
    <div class="offcanvas-header">
        <div class="min-w-0">
            <h2 class="offcanvas-title h5 mb-0" id="objectiveChatTitle">Add an objective</h2>
            <p class="drawer-note">Describe the outcome. The assistant tightens it into one sentence.</p>
        </div>
        <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="gk-drawer-main">
            @if ($tenants->count() !== 1)
                <div>
                    <label class="form-label" for="objectiveTenantSelect">SBU</label>
                    <select id="objectiveTenantSelect" class="form-select">
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input id="objectiveTenantSelect" type="hidden" value="{{ $tenants->first()->id }}">
            @endif

            <div class="logic-thread" id="objectiveThread" aria-live="polite"></div>

            <form class="composer" id="objectiveComposer">
                <button class="mic-btn" id="objectiveMic" type="button" aria-pressed="false" aria-label="Speak an objective">
                    <i class="bi bi-mic" aria-hidden="true"></i>
                </button>
                <textarea id="objectiveMessage" class="form-control" rows="2" maxlength="4000" placeholder="Describe the outcome..." aria-label="Objective message"></textarea>
                <button class="btn btn-gk" type="submit" aria-label="Send"><i class="bi bi-send"></i></button>
            </form>
            <div class="mic-bar d-none" id="objectiveMicBar" role="status">
                <span class="mic-dot" aria-hidden="true"></span>
                <span class="mic-note" id="objectiveMicNote">Listening. Pause when you finish the sentence.</span>
            </div>
        </div>

        <form class="gk-drawer-foot" id="objectiveSaveForm" method="post" action="{{ route('admin.objective.store') }}">
            @csrf
            <input type="hidden" name="_method" id="objectiveSaveMethod" value="POST" disabled>
            <input type="hidden" name="tenant_id" id="objectiveTenantField" value="{{ $tenants->count() === 1 ? $tenants->first()->id : '' }}">
            <input type="hidden" name="title" id="objectiveTitleField">
            <input type="hidden" name="body" id="objectiveBodyField">
            <button class="btn btn-gk w-100" id="objectiveSave" type="submit" disabled>Save objective</button>
        </form>
    </div>
</div>
