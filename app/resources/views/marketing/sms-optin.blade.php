{{--
    The call-to-action page a 10DLC campaign registration points at.

    ⚠️ EVERY CLAIM ON THIS PAGE IS A CLAIM ABOUT CODE THAT EXISTS — `bot.blade.php`'s
    standard, applied to a stricter reader. A carrier reviewer decides whether this
    programme may send at all, and a sentence here that overstates what happens is
    a misrepresentation made to a regulator's proxy rather than marketing copy.

    ⚠️ THE DISCLOSURE PARAGRAPH IS NOT TYPED HERE. It arrives from
    `ConsentDisclosure`, the same source the hosted feedback page renders at the
    real moment of capture, and `SmsOptInPageTest` pins the two together — so the
    words a carrier approves cannot drift from the words a customer is shown.

    ⚠️ THE SAMPLE CHECKBOX IS `disabled` AND SAYS SO IN TEXT. `29` §2 requires
    every consent checkbox to be unchecked by default; showing one that is not
    interactive is honest about this page not being the capture surface, and the
    caption carries that in words rather than in the greying alone (`22`: colour
    and state are never the sole indicator).

    No JavaScript beyond the layout's own deferred bundle: row 1's LCP gate is
    still unmeasured (`STAGE-0-GAPS.md`) and this page must not make it worse.
--}}

<x-marketing.layout
    title="Text messages"
    description="How text messages from a business using this service work, how to start them, and how to stop them."
>
    <article class="mx-auto w-full max-w-2xl px-4 py-16">
        <h1 class="font-display text-3xl font-semibold tracking-tight text-ink">
            Text messages from a business using {{ config('app.name') }}
        </h1>

        <p class="mt-4 text-lg text-ink-2">
            Local businesses use {{ config('app.name') }} to keep in touch with their own
            customers. When one of them texts you, the message is sent through us and says so.
            This page explains what those messages are, how you start them, and how you stop
            them.
        </p>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">How you opt in</h2>

        <p class="mt-3 text-base text-ink-2">
            You give a business your mobile number on their own page — after a visit, at the
            counter, or from a QR code on the table — and tick a box. The box is empty until you
            tick it. Nothing is sent to you until you do, and giving your number is never a
            condition of buying anything.
        </p>

        <p class="mt-3 text-base text-ink-2">
            This is the wording shown beside that box, exactly as it appears at the moment you
            agree. <span class="font-mono text-sm">{{ '{Business}' }}</span> is replaced by the
            name of the business you are giving your number to.
        </p>

        <figure class="mt-4 rounded-[--radius-card] border border-rule bg-card px-4 py-4">
            <div class="flex items-start gap-3">
                <input
                    id="sms-optin-sample"
                    type="checkbox"
                    disabled
                    aria-describedby="sms-optin-sample-note"
                    class="mt-1 size-5 shrink-0 rounded-[--radius-control] border border-rule"
                >
                <label for="sms-optin-sample" class="text-base text-ink">{{ $smsLabel }}</label>
            </div>

            <p class="mt-3 text-base text-ink-2">{!! $smsText !!}</p>

            <figcaption id="sms-optin-sample-note" class="mt-4 border-t border-rule pt-3 text-sm text-ink-2">
                Example only — this box cannot be ticked here. You tick it on the business's own
                page. Wording version <span class="font-mono">{{ $disclosureVersion }}</span>,
                recorded with your agreement so we can always show you what you were asked.
            </figcaption>
        </figure>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">What you get</h2>

        <ul class="mt-3 space-y-2 text-base text-ink-2">
            <li>
                <strong class="text-ink">Messages about your visit.</strong>
                A business you have just dealt with asking how it went, or replying to something
                you told them.
            </li>
            <li>
                <strong class="text-ink">Message frequency varies.</strong>
                There is no schedule. Messages follow something you did, so a customer who
                visits once gets one and nobody gets a daily send.
            </li>
            <li>
                <strong class="text-ink">Message and data rates may apply.</strong>
                Your mobile network charges for these the way it charges for any text.
            </li>
        </ul>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">How you stop them</h2>

        <p class="mt-3 text-base text-ink-2">
            Reply <span class="font-mono text-sm">STOP</span> to any message. It takes effect as
            soon as we receive it, and it stops every message to your number from every business
            using {{ config('app.name') }} — not only from the one that texted you.
            <span class="font-mono text-sm">UNSUBSCRIBE</span>,
            <span class="font-mono text-sm">CANCEL</span>,
            <span class="font-mono text-sm">END</span>,
            <span class="font-mono text-sm">QUIT</span>,
            <span class="font-mono text-sm">REVOKE</span>,
            <span class="font-mono text-sm">STOPALL</span> and
            <span class="font-mono text-sm">OPT OUT</span> do the same thing, in any case, with
            or without punctuation.
        </p>

        {{--
            ⚠️ THE CONFIRMATION IS NOT TYPED HERE. It is the exact string
            `ComplianceReplies::platformStopBody()` sends, and `SmsOptInPageTest` reads it
            back — the same rule as the disclosure paragraph above. A second copy of this
            sentence is the one that would stop matching the first.

            ⚠️ NOT INSIDE A `font-mono text-sm` SPAN, DELIBERATELY. The keyword test on this
            page scrapes exactly that selector and asserts every word it finds is one
            `InboundKeyword::parse()` honours; a whole sentence in one would either break
            that test or force it to be loosened until it caught nothing (511).
        --}}
        <p class="mt-3 text-base text-ink-2">
            We text you back once to confirm, and that is the last message you get. This is
            what it says:
        </p>

        <p class="mt-3 rounded-[--radius-card] border border-rule bg-card px-4 py-3 font-mono text-sm text-ink">
            {{ $stopConfirmation }}
        </p>

        <p class="mt-3 text-base text-ink-2">
            Reply <span class="font-mono text-sm">HELP</span> to ask who is texting you. HELP
            does not stop your messages on its own — reply
            <span class="font-mono text-sm">STOP</span> for that.
        </p>

        <p class="mt-3 text-base text-ink-2">
            If you change your mind, reply <span class="font-mono text-sm">START</span> to the
            same number and your messages resume.
        </p>

        <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">The rest of it</h2>

        <p class="mt-3 text-base text-ink-2">
            <a
                href="{{ $termsUrl }}"
                class="text-ink underline underline-offset-2 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
            >{{ $termsTitle }}</a>
            covers the programme itself.
            <a
                href="{{ $privacyUrl }}"
                class="text-ink underline underline-offset-2 focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
            >{{ $privacyTitle }}</a>
            covers what we hold about you and how to have it removed.
        </p>
    </article>
</x-marketing.layout>
