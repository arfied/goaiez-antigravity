{{--
    The owner's own copy of COMP-02's question, after the wizard (decision 1143).

    location-picker: rendered by livewire/account/settings.blade.php

    ⚠️ THIS PANEL IS NESTED INSIDE `Account\Settings` AND DELIBERATELY DRAWS NO
    PICKER OF ITS OWN (3060–3079). The parent renders one for the whole page,
    above this panel and the timezone panel below it, because both act on the
    same location — two controls setting one cursor would let an owner put two
    halves of one screen out of step with each other. The marker above is what
    `Architecture/AccountScreensTest` reads; its wording is exact and fails
    closed if it drifts.

    ⚠️ THE `@if` IS INSIDE THE ROOT `<div>`, AND IT HAS TO BE. Livewire wraps a
    root-level `@if` in `<!--[if BLOCK]><![endif]-->`, which makes the first `<`
    in this component's HTML the start of a comment rather than of an element —
    and `SupportNestingComponents` derives a nested child's tag with
    `preg_match('/<([a-zA-Z0-9\-]*)/')`, so it records an **empty** tag and then
    throws "Invalid Livewire child tag name" naming the *parent's* view. The
    first render is fine; it dies on the first update, so the panel looks
    correct until an owner clicks something on `/account`.

    ⚠️ THE DISCLOSURE IS HERE IN FULL, NOT SUMMARISED AND NOT COLLAPSED. It is
    the same requirement COMP-02 puts on the wizard and it applies for the same
    reason: this screen records the same `disclosure_version` on its audit entry.
    A shorter version here would make that record false.

    ⚠️ THE ACKNOWLEDGEMENT CHECKBOX WAS REMOVED ON 2026-08-12 (2074, 2660) AND
    THE DISCLOSURE IS WHY IT DID NOT GO WITH IT. Decision 2077: the checkbox was
    one of two places a tenant was told that their own 1–5 answer does not reach
    Trustpilot's forced 0 or Yelp's confirmed-listing path. The rule is enforced
    in code; the explanation only ever lived on a screen, so it stays on one.

    OUTCOME LANGUAGE, NO INTERNAL VOCABULARY (`22`, `29` §2 rule 47). The heading
    says who gets asked; the word "threshold" appears nowhere on the page.
--}}

<div>
    {{--
        ⚠️ ABSENT RATHER THAN DISABLED WHEN THERE IS NO SINGLE LOCATION (1220's
        rule). Gating is a per-location setting and this application has no
        location picker, so there is no honest control to render for a tenant
        with none or with two — and an exception here would take the rest of
        `/account` down with it, Pause Everything included.
    --}}
    @if ($available)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Who we ask for reviews</h2>

            <p class="mt-2 text-base text-ink-2">
                After someone leaves you feedback, we can point them to your Google listing to
                post a public review. You decide who sees that invitation, and you can change
                it whenever you like.
            </p>

            <x-reviews.gating-disclosure class="mt-5" />

            @if ($mayChoose)
                <form wire:submit="save" class="mt-5 space-y-4">
                    <x-reviews.gating-choices model="rating" legend="Who gets asked" />

                    <x-ui.submit target="save" busy="Saving…">Save</x-ui.submit>
                </form>
            @else
                {{--
                    ⚠️ NAMED RATHER THAN HIDDEN, AND NEVER A DISABLED RADIO —
                    `Account\Calls` and `Account\Knowledge` make the same call
                    for the same reason: a greyed-out control reads as a bug in
                    our page, where a sentence naming who can do this reads as
                    the truth and tells them who to ask.

                    ⚠️ AND THE DISCLOSURE STAYS ABOVE IT. 2077's point is that
                    the tenant is told which rules are theirs and which are a
                    platform's; somebody who cannot move the number still works
                    the inbox this number fills, and hiding the explanation from
                    them would leave the one person handling the feedback with
                    no idea why some customers were never asked.
                --}}
                <p class="mt-5 text-base text-ink-2">
                    Someone else sets this. It decides which of your customers are invited
                    to leave a public review, so only an owner or a manager can move it —
                    what you have now is below.
                </p>

                <p class="mt-3 text-base font-medium text-ink">
                    @if ($rating === (string) \App\Services\Reviews\ReviewGating::EVERYONE)
                        Everyone is asked for a review.
                    @elseif ($rating === (string) \App\Services\Reviews\ReviewGating::MAX_THRESHOLD)
                        Only customers who rated you 5 stars are asked for a review.
                    @elseif ($rating === '')
                        Nobody has chosen yet.
                    @else
                        Customers who rated you {{ $rating }} stars or better are asked for a review.
                    @endif
                </p>
            @endif
        </div>
    @endif
</div>
