@php
    use App\Enums\MarketingCapability;
@endphp

{{--
    The twenty questions (CC-2 §2.6).

    ⛔ **AND THE PAGE ITSELF NO LONGER SAYS *TWENTY*, BECAUSE ON EVERY DEPLOYMENT
    IT RENDERS EIGHTEEN — FOUND 2026-08-28 BY READING THE RENDERED PAGE AS TEXT,
    NOT BY ANY ASSERTION (11812).** The array below holds twenty entries and two
    of them are gated on a capability that seeds off, so the count in the intro
    and in the meta description was a hand-kept figure over a list that gates
    itself — 11398's shape, on a public page. **The number is deleted rather than
    corrected to eighteen**: the honest form of a count beside a gated list is no
    count, because the next capability to flip would falsify it again in the
    other direction. ⚠️ **The heading of this comment keeps the word**, because
    it is true of the array and CC-2 §2.6 is what it cites.

    ⚠️ THE ANSWERS THAT DESCRIBE A DARK CAPABILITY DO NOT RENDER. Gift cards ride
    `features.commerce` and the Boost Score rides `features.boost_score`, so this
    page cannot answer "yes, gift cards" while `/features` has no commerce
    section — which is the drift `MarketingCapability` exists to prevent, and the
    FAQ is where it would have happened first, because an answer feels smaller than
    a section.

    ⚠️ THE LIMITED TIER IS NOT NAMED, DELIBERATELY. Its price is unset (157) and
    `/pricing` therefore does not carry a card for it; naming it here as an answer
    would advertise something a reader cannot buy. The answer about carrier
    registration says the true thing — email invites need none — without selling a
    tier that has no price.

    ⚠️ THE REVIEW ANSWERS DESCRIBE `ReviewGating` IN WORDING ONLY. The routing is
    frozen and nothing here changes it.

    ⛔ **THREE ANSWERS ON THIS PAGE OUTRAN THEIR MECHANISM AND WERE REWRITTEN ON
    THE OWNER'S RULING OF 2026-08-28 (11801, 11803, 11810).** Every one of them
    rendered unconditionally — the `@foreach` entries take an optional third
    element and only two carry one — so none of them was ever behind a switch
    somebody could have turned off. `Architecture/MarketingTest` now pins all
    three by their exact words and refuses the three superseded sentences
    anywhere on the funnel, so a revert is a red build rather than a diff nobody
    reads.

      - **STOP.** The old answer said *honored instantly, and forever … nothing
        we send can reach them again … no setting anywhere can undo it*. It was
        false three ways: `Sms/InfobipWebhookVerifier` answers 401 to every
        genuine delivery while `credentials.infobip_webhook_secret` is unset;
        `SuppressionReason::Stop` is the one class that **lifts**, because
        carrier rules oblige us to honour a later START; and the refusal is
        written per channel, so an email is a separate register. ⛔ **The
        replacement had to stay true on both sides of that secret being pasted**
        and must never describe the outage — so it says what happens on receipt,
        which is what `/sms-optin` already tells a carrier reviewer.

      - **The rules about texting.** *"The state-by-state rules are applied"*
        overstated `state_messaging_rules`, which ships with no seeder and whose
        only writer is `compliance:set-state-rule` because the rows are
        counsel's. ⚠️ **The mechanism is real and reached** — the federal floor
        is `Consent/StateMessagingRules::platformFloorRefusal()` and the
        prohibited bands union — so this is softened rather than pulled, and the
        marketing-only carve-out (1618) is now stated because it is the half a
        business owner benefits from.

      - **Approvals.** The old answer was *"Yes"*. ⛔ **CONFIRM IS UNBUILT AND
        THIS REPOSITORY CARRIES A BUILD-FAILING TEST SAYING SO** —
        `tests/Feature/ConfirmIsUnbuiltTest.php`, which asserts that CONFIRM's
        store has no writer and no reader anywhere in `app/` and that the two
        autopilot columns behind it are read by nothing. The Ops field that once
        set the mode was removed at 8568, on the argument that a control which
        visibly does nothing is worse than none, and **the public page went on
        answering yes**. What survives is true and is still a strong sentence:
        `Reviews/ReviewReplies::AUTO_POST_RATING_FLOOR` is a constant rather
        than a column precisely so no configuration can publish a reply to an
        unhappy review without a person.

        ⛔ **AND THAT BULLET NAMES NO COLUMN AND NO TABLE ON PURPOSE — THE FIRST
        DRAFT DID AND TURNED THAT TEST RED** (11811). `Ops/ColumnReaders` does
        not tokenise `.blade.php`, so **a `{{--` comment counts as code** and a
        column named in prose here is scored as having a reader. The class's own
        docblock lists that as a known limitation; this is the first time it has
        cost anything, and it cost a build-failing compliance test on a commit
        whose only other change was marketing copy.
--}}

<x-marketing.layout
    title="Questions, answered"
    description="Straight answers: how fast the text-back is, what STOP does, who owns your data, what it costs, and how to leave."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8 text-center">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            Questions, answered.
        </h1>

        <p class="mx-auto mt-5 max-w-xl text-lg text-ink-2">
            The ones we actually get asked, answered the way we would answer them on
            the phone.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="questions">
        <h2 id="questions" class="sr-only">Questions</h2>

        <dl class="space-y-8">
            @foreach ([
                ['How fast is the text-back?', 'Sixty seconds. That is not a target we aim at, it is the product — a missed call goes out as a text while the person is still holding their phone.'],
                ['What happens when somebody texts STOP?', 'It takes effect the moment we receive it, and it covers every business on this platform rather than only the one that texted them — one refusal, held against the number itself, that no business and no setting of ours can lift. The only thing that lifts it is the same person texting START. Email carries its own unsubscribe and works the same way.'],
                ['Do I need new hardware?', 'No. Your number stays exactly where it is and nothing gets installed on a phone.'],
                ['Will it ever quote the wrong price?', 'It cannot. It answers from your price list and has no way to produce a number that is not on it — that is how it is built, not a promise about how careful it is.'],
                ['What about emergencies?', 'Urgent words escalate. A message that reads like a safety problem is put in front of a person first and answered first, rather than waiting its turn.'],
                ['Can I approve things before they go out?', 'A reply to an unhappy review always comes to you first — that floor is written into the code and no setting can lower it — and you edit it, approve it or skip it in your own queue. A single switch that holds everything else back is not something we have, and we would rather say so than have you find out.'],
                ['Who owns my data?', 'You do. We never sell it, and we never pool one business\'s customers into anything another business can see or search.'],
                ['What are the rules about texting people?', 'They are checked before every message, and one that would break a rule is not sent. An opt-out stops a message outright, on any channel, whoever asked for it. Quiet hours run on the customer\'s own local time under the federal rule, with a stricter state window taking over where a state sets one — and they hold marketing back rather than a reply to something the person has just done.'],
                ['Am I in a contract?', 'Month to month. You can leave whenever you like and nothing holds your number or your data hostage.'],
                ['How do the reviews work?', 'Happy customers are pointed at the public sites. Unhappy ones reach you privately, first — so you get the chance to fix it before anybody else reads it.'],
                ['How long does setup take?', 'About five minutes to the first win. You connect Google once, and the first useful thing happens the same day.'],
                ['Can I cancel easily?', 'In the app, in about a minute. No call, no form, no retention queue.'],
                ['Does it work in my industry?', 'Almost certainly. The front desk answers from your own price list, your own hours and your own words — so what makes a plumber different from a dental office is your information rather than our code.'],
                ['What does it cost?', 'The plan is '.$monthly.' a month, with one location included and a '.$trialDays.'-day free trial that asks for no card. Every figure is on the pricing page.'],
                ['I have more than one location — is that a problem?', 'No. One login covers all of them, each with its own number, its own reviews and its own results.'],
                ['Who writes the replies?', 'Drafts are written for you, in your voice, from what your business actually says. You keep the wheel — change one, or let it go.'],
                ['What if it breaks?', 'You will usually hear from us before you notice, because the same measurements that prove the work also catch it going wrong. And there is a human on the other end.'],
                ['Is there new paperwork before I can text people?', 'For email review invites, none. Full texting goes through carrier registration, which takes real days — we say so up front rather than pretending it is instant.'],
                ['Do you do gift cards?', 'Yes — and they never expire.', MarketingCapability::Commerce->value],
                ['What is the Boost Score?', 'One honest number, from counted data. If there is not enough data to count, there is no number — we do not print a score to fill a space.', MarketingCapability::BoostScore->value],
            ] as $entry)
                @php($capability = $entry[2] ?? null)

                @if ($capability === null || $capabilities[$capability])
                    <div>
                        <dt class="font-display text-lg font-semibold text-ink">{{ $entry[0] }}</dt>
                        <dd class="mt-2 text-base text-ink-2">{{ $entry[1] }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>

        <div class="mt-12 flex flex-wrap gap-3">
            <x-ui.button :href="route('start')">Start free</x-ui.button>
            <x-ui.button :href="route('pricing')" variant="secondary">See the prices</x-ui.button>

            @if ($industryPages)
                <x-ui.button :href="route('industries.index')" variant="secondary">Find your industry</x-ui.button>
            @endif
        </div>
    </section>
</x-marketing.layout>
