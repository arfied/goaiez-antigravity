{{--
    The change history for whichever key this screen has in hand.

    `38` Part 2: "every change is audited with before/after". Showing it is what
    makes the audit visible rather than merely recorded.

    ⚠️ A PARTIAL BECAUSE THERE ARE NOW TWO PLACES A KEY IS IN HAND (5883) — the
    text editor and the site-write confirmation — and two copies of an audit
    rendering are two renderings that drift.

    ⛔ AND EXTRACTING IT IS WHAT MADE ITS EMPTY BRANCH REQUIRED, WHICH IS THE
    LINT WORKING RATHER THAN AN OBSTACLE (5889). Inside the settings screen this
    loop was nested in the per-row loop, so `ScreenStates` classified it as a
    sub-list and never asked; standing alone it is a record list. The sentence
    it now has is one an operator about to authorise writing to customers'
    websites actively wants — "nobody has moved this" is a different fact from a
    blank space, and a blank space is what used to render.

    ⚠️ NO ACTION ON THE INVITATION, DELIBERATELY. The action is the switch a
    press away on the same screen, and a button here would offer the same press
    twice — which on this particular setting is the press the whole slice exists
    to make deliberate.

    ⚠️ `var_export()` on both sides is deliberate: it is the one spelling that
    tells `true` from `'true'`, which is the whole subject of the defect this
    screen was fixed for.
--}}
<ul class="mt-4 space-y-1 text-sm text-ink-2">
    @forelse ($history as $entry)
        <li wire:key="change-{{ $entry->id }}" class="tabular-nums">
            {{ $entry->created_at->format('j M Y H:i') }} ·
            {{ $entry->value_before === null ? 'first set' : var_export($entry->value_before, true) }}
            →
            {{ var_export($entry->value_after, true) }}
            · {{ $entry->actor }}
        </li>
    @empty
        <li>
            <x-ui.empty-state icon="—">
                Nobody has changed this setting. It is still on the default this
                platform shipped with.
            </x-ui.empty-state>
        </li>
    @endforelse
</ul>
