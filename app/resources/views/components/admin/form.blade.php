@props(['fields', 'saved' => false])

{{--
    The one form markup in the application. Controls come from Field, so a
    screen adds a setting by declaring it, not by writing a control.

    Labels are real <label for>, errors are tied to their input with
    aria-describedby, and controls clear 44px — WCAG 2.2 AA, and the error is
    text rather than a red border alone (`29` §2 rule 46).
--}}

<form wire:submit="save" class="space-y-6">
    @if ($saved)
        <p role="status" class="rounded-[--radius-control] bg-ok-bg px-4 py-3 text-base text-ok">
            Saved.
        </p>
    @endif

    @foreach ($fields as $field)
        @php
            $id = 'field-'.$field->key;
            $model = 'state.'.$field->key;
            $errorKey = 'state.'.$field->key;
            $describedBy = collect([
                $field->helpText() ? $id.'-help' : null,
                $errors->has($errorKey) ? $id.'-error' : null,
            ])->filter()->implode(' ');
        @endphp

        <div class="flex flex-col gap-1.5">
            @if ($field->type() === 'checkbox')
                <label for="{{ $id }}" class="flex min-h-11 items-center gap-3 text-base text-ink">
                    <input
                        type="checkbox"
                        id="{{ $id }}"
                        wire:model="{{ $model }}"
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                        class="size-5 rounded-[--radius-control] border-rule-strong"
                    />
                    {{ $field->label }}
                </label>
            @else
                <label for="{{ $id }}" class="text-sm font-medium text-ink-2">{{ $field->label }}</label>

                @if ($field->type() === 'select')
                    <select
                        id="{{ $id }}"
                        wire:model="{{ $model }}"
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    >
                        @foreach ($field->options() ?? [] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif ($field->type() === 'textarea')
                    <textarea
                        id="{{ $id }}"
                        wire:model="{{ $model }}"
                        rows="4"
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                        class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    ></textarea>
                @else
                    <input
                        type="text"
                        id="{{ $id }}"
                        wire:model="{{ $model }}"
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    />
                @endif
            @endif

            @if ($field->helpText())
                <p id="{{ $id }}-help" class="text-sm text-ink-3">{{ $field->helpText() }}</p>
            @endif

            @error($errorKey)
                {{-- Text, not colour alone. --}}
                <p id="{{ $id }}-error" class="text-sm text-alert">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <button
        type="submit"
        class="min-h-11 rounded-[--radius-control] bg-ink px-5 text-base font-medium text-paper"
    >
        {{-- Actions keep their verb through the flow (`29` §2 rule 47). --}}
        <span wire:loading.remove wire:target="save">Save</span>
        <span wire:loading wire:target="save">Saving…</span>
    </button>
</form>
