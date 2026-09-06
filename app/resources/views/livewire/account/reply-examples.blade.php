{{--
    The owner's own examples of a good reply (GBP-03, decision 1732's writer).

    ⚠️ THE `@if` IS INSIDE THE ROOT `<div>`, AND IT HAS TO BE. Livewire wraps a
    root-level `@if` in `<!--[if BLOCK]><![endif]-->`, which makes the first `<`
    in this component's HTML the start of a comment rather than of an element —
    and `SupportNestingComponents` derives a nested child's tag with
    `preg_match('/<([a-zA-Z0-9\-]*)/')`, so it records an empty tag and then
    throws "Invalid Livewire child tag name" naming the parent's view. The first
    render is fine; it dies on the first update, so the panel looks correct until
    an owner clicks something. `livewire/account/review-rules.blade.php` carries
    the same warning for the same reason.

    ⛔ THE EXAMPLE BODIES ARE ESCAPED, AND THIS IS THE ONE PLACE THAT MATTERS.
    `{{ }}` rather than `{!! !!}`: the body is free text an owner typed, it is
    rendered back to whoever else works this account, and a template is now a
    second untrusted input into a prompt as well as into this page. Nothing here
    may ever become `{!! $example->body !!}`.

    OUTCOME LANGUAGE, NO INTERNAL VOCABULARY (`22`, `29` §2 rule 47). The word
    "template" appears nowhere on screen — an owner is pasting a reply they
    liked, not configuring a few-shot block — and the heading says what they get.
--}}

<div>
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Replies you would write yourself</h2>

        <p class="mt-2 text-base text-ink-2">
            Paste up to {{ $capacity }} replies you have written to customers before, and we
            will write yours in the same voice. We copy the style, never the promises — a
            reply of ours will not offer anything you have not already agreed to.
        </p>

        {{--
            ⚠️ THE INVITATION CARRIES NO BUTTON, DELIBERATELY, AND
            `Architecture\ScreenStatesTest` permits exactly that. The one thing
            a person can do from here is the form directly below it on the same
            panel — an action wired to `add` would scroll them four inches, and
            an action wired to nothing is the dead-retry defect the component's
            own docblock exists about. The sentence points at the form instead.

            ⚠️ AND IT IS ONLY SHOWN TO SOMEBODY WHO CAN ACT ON IT. A `staff`
            user reading "add your first one" under a panel with no form would
            be an invitation to do something we then refuse them.
        --}}
        @if ($examples->isEmpty())
            <x-ui.empty-state class="mt-4" heading="No examples yet">
                @if ($mayCurate)
                    Add one below and every reply we write will follow it. Until then we
                    keep to the voice you chose during setup.
                @else
                    Nobody here has added one. We are replying in the voice this business
                    chose during setup.
                @endif
            </x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($examples as $example)
                    <li class="rounded-[--radius-control] border border-rule p-4" wire:key="reply-example-{{ $example->id }}">
                        <p class="text-base font-medium text-ink">{{ $example->name }}</p>

                        <p class="mt-1 text-base text-ink-2">{{ $example->body }}</p>

                        @if ($mayCurate)
                            <div class="mt-3">
                                {{--
                                    ⚠️ A PLAIN BUTTON WITH A NAMED SUBJECT, NOT AN
                                    ICON. Colour is never the signal (`22`) and a
                                    bare bin glyph is colour and shape alone; the
                                    word says what goes.
                                --}}
                                <button
                                    type="button"
                                    wire:click="remove({{ $example->id }})"
                                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                >
                                    Remove this example
                                </button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! $mayCurate)
            {{--
                ⚠️ NAMED RATHER THAN HIDDEN, AND NEVER A DISABLED FIELD —
                `Account\ReviewRules`, `Account\Calls` and `Account\Knowledge`
                all make the same call for the same reason: a greyed-out control
                reads as a bug in our page, where a sentence naming who can do
                this reads as the truth and tells them who to ask.
            --}}
            <p class="mt-5 text-base text-ink-2">
                Someone else sets this. These examples decide how your public replies read,
                so only an owner or a manager can change them — what you have now is above.
            </p>
        @elseif ($atCapacity)
            <p class="mt-5 text-base text-ink-2">
                You have all {{ $capacity }}. Remove one and you can add another.
            </p>
        @else
            <form wire:submit="add" class="mt-5 space-y-4">
                <div>
                    <label for="reply-example-name" class="block text-sm font-medium text-ink">
                        What to call it
                    </label>

                    <input
                        id="reply-example-name"
                        type="text"
                        wire:model="name"
                        maxlength="{{ $nameLimit }}"
                        class="mt-1 w-full rounded-[--radius-field] border {{ $errors->has('name') ? 'border-alert' : 'border-rule' }} bg-paper px-3 py-2 text-base text-ink"
                        @error('name') aria-invalid="true" @enderror
                    />

                    @error('name')
                        <p class="mt-1 text-sm text-alert" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="reply-example-body" class="block text-sm font-medium text-ink">
                        The reply
                    </label>

                    <textarea
                        id="reply-example-body"
                        rows="4"
                        wire:model="body"
                        maxlength="{{ $bodyLimit }}"
                        class="mt-1 w-full rounded-[--radius-field] border {{ $errors->has('body') ? 'border-alert' : 'border-rule' }} bg-paper px-3 py-2 text-base text-ink"
                        @error('body') aria-invalid="true" @enderror
                    ></textarea>

                    @error('body')
                        <p class="mt-1 text-sm text-alert" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.submit size="default" target="add" busy="Saving…">Save this example</x-ui.submit>
                </div>
            </form>
        @endif
    </div>
</div>
