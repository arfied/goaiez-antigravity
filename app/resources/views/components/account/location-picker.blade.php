@props(['locations', 'selected'])

{{--
    Which location this screen is acting on (3060–3079).

    ⚠️ NOT A TENANT-FACING TOGGLE, AND THE DISTINCTION IS LOAD-BEARING.
    `CLAUDE.md` forbids tenant-facing toggles — overruled exactly once, by an
    explicit owner ruling, for the invite threshold (1143) — and this is not one.
    A toggle stores a *preference* that changes what the product does; this is a
    *cursor* that changes which row you are looking at. Nothing here is read by a
    job, an automation or a send decision, and clearing it changes no behaviour.
    See `LocationContext`'s docblock for the full argument.

    ⚠️ RENDERED ONLY WHEN THERE IS SOMETHING TO CHOOSE BETWEEN. The
    overwhelmingly common tenant has one location, and a select with one option
    on four screens is a control that asks a question with one answer. The guard
    is here rather than at each call site so it cannot be got wrong on the fifth
    screen.

    ⚠️ A PLAIN FORM, NOT `wire:model.live`. Submitting reloads the whole screen,
    which is the honest behaviour: everything on these pages is per-location, so
    swapping one panel would leave the rest describing the location the owner
    just left. It also means the control works with no JavaScript at all.

    `22`: outcome language ("Working on"), body text never below 16px
    (`text-base`), a real `<label>` bound to the select, the submit always
    visible rather than an onchange handler a keyboard user cannot escape, and
    no colour carrying meaning — the current location is named in the control
    rather than indicated by a tint.
--}}

@if ($locations->count() > 1)
    <form
        method="POST"
        action="{{ route('account.location.select') }}"
        {{ $attributes->merge(['class' => 'rounded-[--radius-panel] border border-rule bg-card p-5']) }}
        data-location-picker
    >
        @csrf

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="account-location" class="block text-sm font-medium text-ink">
                    Working on
                </label>

                <select
                    id="account-location"
                    name="location"
                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
                    @foreach ($locations as $location)
                        <option
                            value="{{ $location->id }}"
                            @selected($selected !== null && (int) $selected->id === (int) $location->id)
                        >{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="shrink-0">
                {{--
                    A plain button rather than `<x-ui.submit>`: that component is
                    built for a Livewire action and takes a `target` to drive its
                    busy state, and this form is an ordinary POST with a full
                    page load behind it. Matching its shape without its mechanism
                    would be a busy state that never fires.
                --}}
                <button
                    type="submit"
                    class="w-full rounded-[--radius-control] border border-ink bg-ink px-4 py-2 text-base font-medium text-paper focus:ring-2 focus:ring-ink focus:ring-offset-2 focus:outline-none sm:w-auto"
                >Switch</button>
            </div>
        </div>

        @error('location')
            {{-- Not colour alone (`22`): the message says what is wrong in words. --}}
            <p class="mt-2 text-base text-ink">{{ $message }}</p>
        @enderror
    </form>
@endif
