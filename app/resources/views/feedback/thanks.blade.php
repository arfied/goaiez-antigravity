{{--
    The post-submit screen and FPR-04b's destination picker (slice F).

    NO JAVASCRIPT, AND THAT IS A REQUIREMENT RATHER THAN A PREFERENCE.
    BUILD-PLAN §2.6.3 makes "the picker renders with JavaScript disabled and is
    usable at 320px" one of this row's tests. Every button here is a plain
    anchor to a first-party route that records the click server-side and then
    redirects — see FeedbackPageController::destination() for why a direct link
    plus a beacon was rejected.

    EVERY DESTINATION IS ON THIS SCREEN AT ONCE. `24` §2.3.3: on mobile a review
    link deep-links into the platform's own app, and the customer does not come
    back. So the choice has to be offered while we still have their attention —
    never "pick one, return, pick another".

    RENDERS FROM `routed_destinations`, NEVER FROM `status`. Decision 375: triage
    wins the status column when a review is both invited and triaged, so a picker
    keyed on status would silently vanish for exactly the customer Trustpilot's
    terms require us to invite.

    THE ORDER IS THE ENUM'S, SO GOOGLE IS FIRST. ReviewInvites hands these over
    already sorted by DestinationSettings::offeredFor(); this file must not
    re-sort, or "Google primed" becomes two sources of truth.
--}}

<x-feedback.layout :business-name="$businessName">
    @if ($triaged)
        <h1 class="font-display text-3xl font-semibold tracking-tight">
            {{ __('feedback.thanks.triage.heading') }}
        </h1>

        <p class="mt-3 text-ink-2">{{ __('feedback.thanks.triage.body') }}</p>
    @else
        <h1 class="font-display text-3xl font-semibold tracking-tight">
            {{ __('feedback.thanks.heading') }}
        </h1>

        <p class="mt-3 text-ink-2">{{ __('feedback.thanks.body') }}</p>
    @endif

    @if ($options !== [])
        {{--
            A separated block rather than a continuation of the paragraph above,
            because after triage these are two different messages: we heard you,
            and — separately — here is somewhere public if you want it.
        --}}
        <section class="mt-10 border-t border-rule pt-8">
            @if ($triaged)
                <h2 class="font-display text-xl font-semibold tracking-tight">
                    {{ __('feedback.thanks.picker.after_triage') }}
                </h2>
            @else
                <h2 class="font-display text-xl font-semibold tracking-tight">
                    {{ __('feedback.thanks.picker.heading') }}
                </h2>

                <p class="mt-2 text-ink-2">{{ __('feedback.thanks.picker.body') }}</p>
            @endif

            {{--
                Stacked full-width at every size. At 320px a row of three
                buttons puts each platform's name in a column narrower than the
                word, and this is a one-handed, one-tap decision — the giant
                variant's 44px floor is the WCAG 2.2 target size.
            --}}
            {{--
                `noopener` is what stops the opened tab reaching back through
                window.opener; `noreferrer` keeps this tenant's slug out of the
                platform's logs. `17` FPR-04b names both, and a test asserts
                both are present on every rendered destination link.
            --}}
            <div class="mt-6 flex flex-col gap-3">
                @foreach ($options as $option)
                    <x-ui.button
                        :href="$option->url"
                        :variant="$option->isPrimed() ? 'primary' : 'secondary'"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full"
                    >
                        {{ $option->label() }}
                        <span class="sr-only">{{ __('feedback.thanks.picker.new_tab') }}</span>
                    </x-ui.button>
                @endforeach
            </div>
        </section>
    @endif

    {{--
        The business's own booking link (T176 P7, R13 + R14).

        R13 IS THE WHOLE OF THE CONDITION. A business that has set no booking
        link gets no block — not a disabled button, not "booking coming soon",
        not an address we guessed. `$booking` is null and this section does not
        exist. The controller also passes null after triage; its docblock says
        why, and that reasoning is deliberately not the picker's.

        R14 DOES NOT APPLY HERE AND THE ANCHOR IS THE PROOF. Every link on this
        page is followed by somebody already looking at it, so there is no send
        to mint a per-send token against — see App\Services\Links\BookingLink.

        `secondary`, AND UNDER THE PICKER. The picker is what this screen is for;
        a primary-weighted booking button above it would compete with the review
        invitation for the same tap. Same 44px floor, same full width at 320px.

        `noopener noreferrer` FOR THE PICKER'S OWN REASONS: the opened tab must
        not reach back through window.opener, and this tenant's slug is a stable
        public identifier for one location that a third-party scheduler has no
        business receiving in a referer header.
    --}}
    @if ($booking !== null)
        <section class="mt-10 border-t border-rule pt-8">
            <h2 class="font-display text-xl font-semibold tracking-tight">
                {{ __('feedback.thanks.booking.heading') }}
            </h2>

            <div class="mt-6">
                <x-ui.button
                    :href="$booking->destination()"
                    variant="secondary"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-full"
                >
                    {{ $booking->label }}
                    <span class="sr-only">{{ __('feedback.thanks.booking.new_tab') }}</span>
                </x-ui.button>
            </div>
        </section>
    @endif
</x-feedback.layout>
