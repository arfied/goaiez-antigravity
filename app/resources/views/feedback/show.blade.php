{{--
    The hosted feedback page (`17` FPR-01) — the door every first-party review
    enters through.

    EVERY CONTROL IS NATIVE. The rating is a radio group styled as stars, not a
    JavaScript widget: radios are keyboard-operable and arrow-navigable for free,
    they submit with JavaScript disabled, and the accessible name of each is
    "3 stars" — for a screen reader. For a sighted person, the state is the
    glyph's own shape (outline vs. solid), not only its colour: `22` says colour
    is never the sole indicator, and a solid star that only swapped colour on
    selection would fail that for exactly the colour-blind share of this
    audience `22` names.

    THE STAR FILL USES A PLAIN `peer`/`peer-checked`, NOT A NAMED PEER GROUP PER
    STAR. A named group (`peer/star-3`) would need Tailwind's class scanner to
    see the literal string `peer-checked/star-3:…` in the source, but the value
    here is interpolated, so the scanner never emits it and the row renders with
    no fill at all — a visually broken control that would still pass every
    assertion below. A radio group has at most one `:checked` and one
    `:focus-visible` element at a time, so the general-sibling combinator that
    `peer`/`peer-checked` compiles to needs no name to disambiguate: whichever
    radio is checked recolours every label that follows it in the (reversed)
    source order, which is exactly the stars at or below the chosen rating.

    THE GLYPH ITSELF IS A `::before` ON THE LABEL, NOT A CHILD SPAN, because
    `peer-checked:` only reaches elements that are themselves a later sibling of
    the checked input — a span nested inside the label is a sibling's
    descendant, not a sibling, so the selector would never reach it. The label
    qualifies, so its pseudo-element does too, and `content` swaps from the
    outline star to the solid one the same way `color` already did.

    THE TWO CONSENT BOXES ARE SEPARATE AND UNCHECKED. `24` §3.2: "Separate
    checkbox per channel — SMS consent is not email consent", and a pre-checked
    box is not consent in any jurisdiction that matters. `29` §12.1 puts that on
    the build-failing list; ConsentCapture enforces it at the record, and this is
    the render it is enforcing against.

    OLD INPUT IS REPOPULATED EXCEPT FOR THE CONSENT BOXES. old('sms_consent')
    would faithfully re-tick a box after a validation error, and a box the person
    is shown already ticked is a pre-checked box no matter how it got that way.

    THE DISCLOSURE'S LINKS ARE PART OF ITS VERSIONED WORDING, NOT A SEPARATE
    LINE. ConsentDisclosure keeps its closing links sentence inside
    `sms_text`/`email_text` so the version string can never drift from what the
    links actually say (see that class's docblock — deliberately not quoted
    here, so ArchitectureTest's lint stays the only place that phrase is
    allowed to appear outside `lang/en/feedback.php` and that class itself).
    `$smsText`/`$emailText` below are therefore already-escaped HTML with the
    two anchor phrases turned into links by ConsentDisclosure — rendered with
    `{!! !!}` because they are HTML, not because they are trusted input; the
    escaping happened, and the business name was substituted in, only after
    those two phrases were already anchors (see that class's `html()` for why
    the order is what keeps a business named "Fair Terms Auto" from getting
    part of its own name turned into a link).
--}}

<x-feedback.layout :business-name="$businessName">
    <h1 class="font-display text-3xl font-semibold tracking-tight">
        {{ __('feedback.heading') }}
    </h1>

    <p class="mt-2 text-ink-2">{{ __('feedback.intro') }}</p>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-[--radius-card] border border-rule-strong bg-card p-4">
            <ul class="list-disc space-y-1 ps-5 text-ink">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('feedback.store', ['slug' => $page->slug]) }}" class="mt-8">
        @csrf

        {{--
            The honeypot. Positioned off-screen rather than display:none, and
            labelled and aria-hidden, so a screen reader skips it and a naive
            bot filling every input still fills it.
        --}}
        <div aria-hidden="true" class="absolute -left-[9999px]">
            <label for="website">Website</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>

        <fieldset>
            <legend class="font-semibold">{{ __('feedback.rating.legend') }}</legend>

            {{--
                The stars are written 5..1 and visually reversed with
                flex-row-reverse: the general-sibling combinator behind
                `peer-checked` only reaches *later* siblings, so writing the
                highest value first in the DOM is what lets checking any radio
                colour every star at or below it once the row is unreversed on
                screen. Keyboard order follows the DOM, which is why each input
                carries an explicit accessible name rather than relying on
                position.
            --}}
            <div class="mt-3 flex flex-row-reverse justify-end gap-1">
                @foreach ([5, 4, 3, 2, 1] as $value)
                    <input
                        type="radio"
                        name="rating"
                        id="rating-{{ $value }}"
                        value="{{ $value }}"
                        class="peer sr-only"
                        @checked(old('rating') !== null && (int) old('rating') === $value)
                    >
                    <label
                        for="rating-{{ $value }}"
                        class="cursor-pointer rounded-[--radius-control] p-1 text-3xl leading-none text-ink-3 before:content-['\2606'] hover:text-attention peer-checked:text-attention peer-checked:before:content-['\2605'] peer-focus-visible:ring-2 peer-focus-visible:ring-ink"
                    >
                        <span class="sr-only">{{ trans_choice('feedback.rating.star', $value, ['count' => $value]) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="mt-8">
            <label for="comment" class="font-semibold">{{ __('feedback.comment.label') }}</label>
            <textarea
                name="comment"
                id="comment"
                rows="4"
                maxlength="2000"
                placeholder="{{ __('feedback.comment.placeholder') }}"
                class="mt-2 block w-full rounded-[--radius-control] border border-rule-strong bg-card p-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
            >{{ old('comment') }}</textarea>
        </div>

        <fieldset class="mt-8">
            <legend class="font-semibold">{{ __('feedback.contact.heading') }}</legend>

            <div class="mt-3 space-y-4">
                <div>
                    <label for="name" class="block text-ink-2">{{ __('feedback.contact.name') }}</label>
                    <input type="text" name="name" id="name" maxlength="100" autocomplete="name"
                        value="{{ old('name') }}"
                        class="mt-1 block w-full rounded-[--radius-control] border border-rule-strong bg-card p-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none">
                </div>

                <div>
                    <label for="email" class="block text-ink-2">{{ __('feedback.contact.email') }}</label>
                    <input type="email" name="email" id="email" maxlength="255" autocomplete="email"
                        value="{{ old('email') }}"
                        class="mt-1 block w-full rounded-[--radius-control] border border-rule-strong bg-card p-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none">
                </div>

                <div>
                    <label for="phone" class="block text-ink-2">{{ __('feedback.contact.phone') }}</label>
                    <input type="tel" name="phone" id="phone" maxlength="30" autocomplete="tel"
                        value="{{ old('phone') }}"
                        class="mt-1 block w-full rounded-[--radius-control] border border-rule-strong bg-card p-3 text-base text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none">
                </div>
            </div>
        </fieldset>

        <div class="mt-8 space-y-5">
            <div class="flex gap-3">
                {{-- No @checked directive here, and that is the point. --}}
                <input type="checkbox" name="sms_consent" id="sms_consent" value="1"
                    class="mt-1 size-5 shrink-0 rounded-[--radius-control] border-rule-strong">
                <label for="sms_consent" class="text-base">
                    <span class="font-semibold">{{ $smsLabel }}</span>
                    <span class="mt-1 block text-base text-ink-2">{!! $smsText !!}</span>
                </label>
            </div>

            <div class="flex gap-3">
                <input type="checkbox" name="email_consent" id="email_consent" value="1"
                    class="mt-1 size-5 shrink-0 rounded-[--radius-control] border-rule-strong">
                <label for="email_consent" class="text-base">
                    <span class="font-semibold">{{ $emailLabel }}</span>
                    <span class="mt-1 block text-base text-ink-2">{!! $emailText !!}</span>
                </label>
            </div>

            {{--
                THE THIRD BOX IS NOT A CONTACT CONSENT, AND ONLY A COVERED ENTITY
                RENDERS IT (2079-2081). It asks the reviewer to leave health
                information out of their own comment, and it decides whether this
                one review's words may reach a model at all.

                No @checked here either, for the two above's reason. And no
                `required`: a rating is captured and kept whatever the reviewer
                decides (2075), so declining costs them the automated read and
                never their voice.

                `$phiAnalysisText` is plain text and is escaped like any other
                string. The two disclosures above are `{!! !!}` only because
                ConsentDisclosure turned their two anchor phrases into links;
                this wording carries neither phrase and needs neither.
            --}}
            @if ($showPhiAnalysisConsent)
                <div class="flex gap-3">
                    <input type="checkbox" name="phi_analysis_consent" id="phi_analysis_consent" value="1"
                        class="mt-1 size-5 shrink-0 rounded-[--radius-control] border-rule-strong">
                    <label for="phi_analysis_consent" class="text-base">
                        <span class="font-semibold">{{ $phiAnalysisLabel }}</span>
                        <span class="mt-1 block text-base text-ink-2">{{ $phiAnalysisText }}</span>
                    </label>
                </div>
            @endif
        </div>

        <x-ui.button type="submit" class="mt-8 w-full">{{ __('feedback.submit') }}</x-ui.button>
    </form>
</x-feedback.layout>
