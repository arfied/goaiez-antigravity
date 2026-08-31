{{--
    COMP-02's disclosure, shared by the wizard step and the owner's settings
    screen.

    ⚠️ IT IS A COMPONENT BECAUSE THERE ARE NOW TWO SCREENS, NOT BECAUSE IT IS
    TIDIER. Decision 1143 made the threshold the tenant's to change, so the
    choice is made from two places — and the audit entry records *what the owner
    read*. Two copies of this text would drift, and the drift would be
    invisible: both screens would keep recording the same `disclosure_version`
    while showing different words, which is precisely the thing
    `ReviewRules::DISCLOSURE_VERSION` exists to make answerable.

    ⚠️ UNCONDITIONAL AND ABOVE THE CHOICE. COMP-02 requires it "visible on the
    screen rather than behind an 'Advanced' toggle", and both screens' tests
    assert its four required subjects are present — Google's policy,
    Trustpilot's terms, Yelp's position, the FTC's. A warning an owner can choose
    not to open is not a warning, and a collapsed one is a warning they will not
    open.

    ⚠️ THIS IS NOW THE ONLY SURFACE THAT EXPLAINS ANY OF IT, AND THAT IS WHY THE
    LAST TWO PARAGRAPHS WERE ADDED (decisions 2074, 2077, 2663). The
    acknowledgement checkbox that used to sit under this block was the second
    place a tenant was told which of these rules are theirs and which are a
    platform's; the owner removed it on 2026-08-11. **The rule did not go with
    it** — Trustpilot is pinned at 0 by `ReviewDestination::forcedThreshold()`
    and Yelp exists only through the confirmed-listing path (1161), both
    enforced in code and in a CHECK. What went was the explanation, so the
    explanation is restored here as plain copy.

    ⚠️ NO OPTION IS RECOMMENDED HERE OR IN THE LABELS. The honest position — that
    asking everyone is the safer one — is stated here, where it is argued. A
    radio label that editorialised while the others did not would be a dark
    pattern rather than a disclosure.
--}}

<div {{ $attributes->merge(['class' => 'rounded-[--radius-card] border border-rule-strong bg-card p-4']) }}>
    <h2 class="text-lg font-medium">Before you choose, the part that matters</h2>

    <p class="mt-3 text-base">
        Asking only your happiest customers for reviews is called review gating, and the
        platforms have positions on it.
    </p>

    <ul class="mt-3 space-y-3 text-base">
        <li>
            <strong>Google</strong> prohibits selectively soliciting positive reviews.
            Listings found doing it can have reviews removed, and repeat cases can lose
            review features altogether.
        </li>
        <li>
            <strong>Trustpilot</strong> goes further: their terms require that you invite
            every customer, so we never gate Trustpilot. If you use it, it always asks
            everyone.
        </li>
        <li>
            <strong>Yelp</strong> asks businesses never to solicit reviews at all, and can
            put a public alert on your Yelp page for it. So Yelp is only ever added by you,
            by pasting and confirming your own listing — we never add it for you, and never
            switch it on by default.
        </li>
        <li>
            <strong>The FTC</strong> treats suppressing negative reviews as a deceptive
            practice, with penalties per violation.
        </li>
    </ul>

    <p class="mt-3 text-base">
        The narrower your choice, the more exposed you are — asking only your five-star
        customers is the strictest version of the practice they describe.
    </p>

    <p class="mt-3 text-base">
        Your choice above is your own rule, and it does not override any of theirs. Where a
        platform sets its own terms, those win: Trustpilot always asks everyone whatever you
        pick here, and Yelp only ever appears if you added it yourself.
    </p>

    <p class="mt-3 text-base">
        Whichever you pick, everyone who leaves you feedback still reaches you privately —
        unhappy customers are never dropped, they go to your inbox so you can put it right.
    </p>
</div>
