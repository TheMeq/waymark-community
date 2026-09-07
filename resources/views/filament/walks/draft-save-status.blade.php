<div
    x-data
    x-on:walk-draft-save-failed.window="$nextTick(() => $refs.draftSaveError?.focus())"
>
    <p role="status" aria-live="polite" aria-atomic="true">
        {{ $this->draftSaveStatus }}
    </p>

    @if ($this->draftSaveError)
        <p role="alert" tabindex="-1" x-ref="draftSaveError">
            {{ $this->draftSaveError }}
        </p>
    @endif
</div>
