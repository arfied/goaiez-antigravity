@props(['sign'])

{{--
    One location's feedback page, as something to print — the card itself, and
    the controls that must not be on it.

    ⛔ ONE COPY OF THIS TEXT, AND THAT IS WHY IT IS A COMPONENT (9157). It was
    inline in `components/account/review-sign` until the onboarding wizard's
    third step needed the same card. `components/reviews/gating-disclosure`
    records the identical argument one screen over: two copies of compliance-
    governed copy is two copies that can disagree, and the one that drifts is
    invisible.

    ⛔ THE COPY ON THE CARD IS A REVIEW INVITATION AND `29` §2 RULE 1 GOVERNS IT.
    No offer, no discount, no prize, nothing anybody gets for scanning — an
    incentivised review is a fake review with extra steps, and it is against
    Google's and Yelp's terms as well as ours. `ReviewInviteEmail` and
    `MissedCallTextBack` carry the same rule in their own docblocks.

    ⛔ AND IT MUST NOT ASK ONLY THE HAPPY ONES. "Loved your visit? Scan here" is
    review gating printed on card, and it is the exact thing decision 2075's
    build-failing test exists to keep out of this product: every rating is
    captured and kept, and none is deleted, suppressed or hidden. "How did we
    do?" is the feedback page's own heading, word for word, so a customer meets
    the same neutral question on the card and on the screen it opens.

    ⚠️ IT LEADS TO THE FEEDBACK PAGE AND NEVER TO A REVIEW SITE. Which
    destination somebody is offered is decided after they rate, from that
    location's own confirmed listings (`24` §2.3, decisions 1161–1162) — a code
    printed straight to Google would take that decision at the printer, months
    early, for every customer at once.

    ⚠️ THE ADDRESS IS PRINTED AS TEXT UNDER THE CODE, AND THAT IS THE ACCESSIBLE
    PATH RATHER THAN A NICETY. A QR is unreadable to a screen-reader user and
    unusable to somebody with no camera; the 2FA screen prints the typed secret
    beside its QR for the same reason (`22`, WCAG 2.2 AA).

    ⚠️ NOTHING ON THE CARD SAYS GO AI EZ. `components/feedback/layout` records
    the rule: a tenant's customer-facing surface does not advertise us to their
    customers.

    COLOUR IS NEVER THE SOLE SIGNAL — every state here is a sentence — and the
    card is pure black on white because that is what a phone camera reads.

    WORKS AT 320px: the card is fluid, the code is capped by `max-w`, and
    nothing here is below 16px.

    ⚠️ THE CONTROLS SIT OUTSIDE `.review-sign`, WHICH IS WHAT KEEPS THEM OFF THE
    PAPER. The print rules in `resources/css/app.css` show that one element and
    hide the rest of the page, and `ReviewSignTest`'s *"the printed card is the
    only thing on the page"* reads the DOM as a tree to prove no button or link
    ever drifts inside it. Anything a caller adds rides in `$slot`, beside the
    print control and outside the card.

    ⚠️ THE PRINT BUTTON IS THE ONLY SCRIPTED THING HERE AND NOTHING DEPENDS ON
    IT (decision 387). With scripting off the card still renders and the
    browser's own Print command still produces it, which is why this is an
    Alpine handler rather than anything the server has to know about.
--}}

<div class="review-sign rounded-[--radius-panel] border border-rule-strong bg-card p-6 text-center">
    <p class="font-display text-lg font-semibold text-ink">{{ $sign->locationName }}</p>

    <p class="mt-2 font-display text-3xl font-semibold text-ink">How did we do?</p>

    {{--
        ⛔ `{!! !!}` BECAUSE IT IS MARKUP THIS APPLICATION BUILT, NOT BECAUSE
        ANYTHING HERE IS TRUSTED. Every character of the SVG comes from
        `QrCodeSvg`: integers out of the encoder, and one accessible name that
        class escapes itself. No tenant string reaches it — the business name
        above is escaped normally.
    --}}
    <div class="review-sign__qr mx-auto mt-5 w-56 max-w-full">
        {!! $sign->svg !!}
    </div>

    <p class="mt-5 text-base text-ink">
        Point your phone camera at the code, or go to
    </p>

    <p class="mt-1 font-mono text-base break-all text-ink">{{ $sign->displayUrl }}</p>

    <p class="mt-4 text-base text-ink-2">
        It takes about twenty seconds, and it goes straight to the owner.
    </p>
</div>

<div class="mt-3 flex flex-wrap gap-3">
    <x-ui.button type="button" size="default" x-on:click="window.print()">
        Print this sign
    </x-ui.button>

    {{ $slot }}
</div>
