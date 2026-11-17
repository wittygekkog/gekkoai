{{-- Shared create form for a rule or an instruction. $kind selects which tab owns it. --}}
<section class="gk-card">
    <h2 class="block-title">{{ $heading }}</h2>
    <form method="post" action="{{ route('admin.logic.store') }}">
        @csrf
        <input type="hidden" name="kind" value="{{ $kind }}">
        @if ($tenants->count() === 1)
            <input type="hidden" name="tenant_id" value="{{ $tenants->first()->id }}">
        @else
            <label class="form-label" for="tenant_id_{{ $kind }}">SBU</label>
            <select id="tenant_id_{{ $kind }}" name="tenant_id" class="form-select mb-3" required>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(old('kind') === $kind && old('tenant_id') === $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        @endif
        <label class="form-label" for="title_{{ $kind }}">Title</label>
        <input id="title_{{ $kind }}" name="title" class="form-control mb-3" value="{{ old('kind') === $kind ? old('title') : '' }}" required maxlength="180">
        <label class="form-label" for="body_{{ $kind }}">{{ $bodyLabel }}</label>
        <textarea id="body_{{ $kind }}" name="body" class="form-control mb-3" rows="4" required maxlength="5000">{{ old('kind') === $kind ? old('body') : '' }}</textarea>
        @if ($kind === 'instruction')
            <label class="form-label" for="intent_{{ $kind }}">Intent</label>
            <input id="intent_{{ $kind }}" name="intent" class="form-control mb-3" value="{{ old('kind') === 'instruction' ? old('intent') : '' }}" required maxlength="120" placeholder="booking.create">
            @error('intent')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
        @endif
        <button class="btn btn-gk" type="submit">Save</button>
    </form>
</section>
