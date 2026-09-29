{{--
    The hosted review hub (`29` §7.6, Appendix A's `/r/{slug}`) — one public page
    per location, listing the reviews its owner approved for publication.

    ⛔ THE RATING AND THE LIST ARE TWO DIFFERENT POPULATIONS AND THE PAGE SAYS SO.
    `$rating` is over every first-party review this location has received;
    `$reviews` is what the owner approved for display, newest first. On a
    location where the owner has declined a review they are different numbers,
    and `review_hub.rating.basis` is the sentence that stops a reader taking the
    star rating to describe the reviews beneath it. `29` §2 rule 5 forbids a
    filtered aggregate — this is how a reader can see that this one is not.

    ⛔ NEVER COMPUTE A RATING IN THIS FILE. Not `count($reviews)`, not an average
    over them, not "3 five-star reviews". The rating comes from
    App\Services\Reviews\TrueRating and arrives already computed; a lint fails
    the build if any other file in app/ averages a rating, and a template that
    did the arithmetic instead would be outside that lint's reach entirely.

    COLOUR CARRIES NOTHING AT ALL HERE, WHICH IS ONE STEP STRONGER THAN `22`
    ASKS. `22` requires colour never be the sole indicator; the stars go further
    and use no signal colour, because the signal palette means running,
    attention and action, and an amber star would spend that vocabulary telling
    a stranger their review needs attention. The state is the glyph's own shape —
    solid for a filled star, outline for an empty one — and every row carries a
    text label ("4 stars") that a screen reader announces once. There is no
    JavaScript on this page, so what a test asserts is what a visitor sees.

    NO REVIEWER CONTACT DETAIL REACHES THIS FILE. HubReviewResource is an
    allowlist of four fields and it is what `$reviews` holds — the model is not
    in scope here, so there is nothing to render by accident.
--}}

<x-review-hub.layout :business-name="$businessName" :json-ld="$jsonLd">
    <h1 class="font-display text-3xl font-semibold tracking-tight">
        {{ __('review_hub.heading') }}
    </h1>

    @if ($rating !== null)
        <section class="mt-6 rounded-lg border border-rule bg-card p-5" aria-labelledby="hub-rating">
            <p id="hub-rating" class="font-display text-4xl font-semibold tracking-tight">
                {{ __('review_hub.rating.summary', ['average' => number_format($rating->average, 1)]) }}
            </p>

            <p class="mt-1 text-sm text-ink-2">
                {{ trans_choice('review_hub.rating.basis', $rating->count, [
                    'count' => number_format($rating->count),
                    'business' => $businessName,
                ]) }}
            </p>
        </section>
    @endif

    <section class="mt-10" aria-labelledby="hub-reviews">
        <h2 id="hub-reviews" class="font-display text-xl font-semibold tracking-tight">
            {{ __('review_hub.reviews.heading') }}
        </h2>

        @if ($reviews === [])
            <p class="mt-4 text-ink-2">{{ __('review_hub.reviews.none') }}</p>
        @else
            <p class="mt-1 text-sm text-ink-2">
                {{ trans_choice('review_hub.reviews.showing', count($reviews), ['count' => count($reviews)]) }}
            </p>

            <ul class="mt-6 space-y-6">
                @foreach ($reviews as $review)
                    <li class="rounded-lg border border-rule bg-card p-5">
                        {{--
                            The accessible name is the whole claim ("4 stars"),
                            and the glyphs are aria-hidden so a screen reader
                            hears it once rather than five times.
                        --}}
                        <p class="text-sm font-medium">
                            <span class="sr-only">{{ trans_choice('review_hub.rating.stars', $review['rating'], ['count' => $review['rating']]) }}</span>
                            <span aria-hidden="true" class="text-ink">
                                {{ str_repeat('★', $review['rating']) }}<span class="text-ink-3">{{ str_repeat('☆', 5 - $review['rating']) }}</span>
                            </span>
                        </p>

                                                @if (($review['ticket'] ?? null) !== null)
                            <div class="mt-3 flex items-center gap-2">
                                @php
                                    $ticketState = str_starts_with($review['ticket']->status, 'open') ? 'attention' : 'ok';
                                    $ticketLabel = str_starts_with($review['ticket']->status, 'open') ? 'Open' : 'Resolved';
                                    if (str_starts_with($review['ticket']->status, 'open') && $review['ticket']->sla_due_at && $review['ticket']->sla_due_at->isPast()) {
                                        $ticketState = 'alert';
                                        $ticketLabel = 'Breached';
                                    }
                                @endphp
                                <x-ui.status-pill :state="$ticketState" :label="$ticketLabel" />
                                <span class="text-[11px] text-ink-2">
                                    Ticket #{{ $review['ticket']->id }} &middot; due {{ $review['ticket']->sla_due_at ? $review['ticket']->sla_due_at->diffForHumans() : 'N/A' }}
                                </span>
                            </div>
                        @endif

                        @if (($review['comment'] ?? null) !== null && trim((string) $review['comment']) !== '')
                            <p class="mt-3 whitespace-pre-line">{{ $review['comment'] }}</p>
                        @endif

                        <p class="mt-3 text-sm text-ink-2">
                            {{-- Null travels as null from the resource; the label is this page's to choose. --}}
                            {{ ($review['author'] ?? null) !== null && trim((string) $review['author']) !== ''
                                ? $review['author']
                                : __('review_hub.reviews.anonymous') }}

                            @if ($review['posted_at'] !== null)
                                <span aria-hidden="true"> · </span>
                                <time datetime="{{ $review['posted_at']->toDateString() }}">{{ $review['posted_at']->isoFormat('D MMMM YYYY') }}</time>
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{--
        The one link off this page, and it goes to this same location's own
        feedback form rather than anywhere of ours. components/feedback/layout
        records the reasoning: a business's hosted page is not somewhere to
        advertise us to their customers.
    --}}
    <section class="mt-12 border-t border-rule pt-8">
        <h2 class="font-display text-lg font-semibold tracking-tight">{{ __('review_hub.leave.heading') }}</h2>

        <div class="mt-6">
            <x-ui.button :href="route('feedback.show', ['slug' => $page->slug])" size="default">
                {{ __('review_hub.leave.action') }}
            </x-ui.button>
        </div>
    </section>
</x-review-hub.layout>
