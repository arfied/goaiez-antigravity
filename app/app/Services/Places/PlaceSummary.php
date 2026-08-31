<?php

declare(strict_types=1);

namespace App\Services\Places;

/**
 * One place, reduced to the fields the audit's four checks actually read.
 *
 * A DTO rather than the raw response array, for one reason that matters more
 * than tidiness: **every field here has a price**. PlacesSku bills a request at
 * the highest tier any requested field belongs to, so a response array invites a
 * later reader to use a field the field mask did not ask for — which either
 * returns null mysteriously, or gets "fixed" by widening the mask and doubling
 * the cost of every call in the system. A closed shape makes the mask and the
 * consumer agree by construction.
 *
 * Nothing here is stored against a tenant. On the public path these values live
 * only in `public_audits.findings` and `name_snapshot`, both of which prune at
 * 90 days (decision 192).
 */
final readonly class PlaceSummary
{
    /**
     * @param  list<array{author: ?string, hasOwnerReply: bool}>  $reviews
     *                                                                      Only what reply-rate needs. Review text is
     *                                                                      deliberately dropped on arrival — the audit
     *                                                                      counts replies, it never republishes a review.
     * @param  list<string>  $types
     * @param  list<string>  $requestedFields
     *                                         The top-level field-mask leaves that produced this
     *                                         summary, `places.` prefix stripped. See
     *                                         {@see self::askedFor()} — this is what makes "Google
     *                                         sent no number" distinguishable from "we never asked
     *                                         for one", and it defaults to the empty list so a
     *                                         summary that says nothing about its mask claims
     *                                         nothing.
     */
    public function __construct(
        public string $placeId,
        public ?string $displayName = null,
        public ?string $formattedAddress = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $primaryType = null,
        public array $types = [],
        public ?float $rating = null,
        public ?int $userRatingCount = null,
        public int $photoCount = 0,
        public bool $hasOpeningHours = false,
        public ?string $editorialSummary = null,
        public ?string $websiteUri = null,
        public ?string $nationalPhoneNumber = null,
        public array $reviews = [],
        public array $requestedFields = [],
    ) {}

    /**
     * Whether the field mask that produced this summary asked for a field.
     *
     * ⛔ **THE MASK IS PART OF THE ANSWER AND WAS NOT CARRIED UNTIL 2026-08-26.**
     * Every optional field on this object is null, `0` or `false` for **two**
     * unrelated reasons — Google had nothing to say, or nobody asked — and this
     * class's own docblock has warned since it shipped that a later reader would
     * "use a field the field mask did not ask for", which "either returns null
     * mysteriously, or gets fixed by widening the mask". It did not say what
     * happens when the reader instead *defaults* the null, which is that a
     * business is told a fact about itself that came from our own request
     * headers. Text Search's mask requests neither `rating` nor
     * `userRatingCount`; its summaries are therefore indistinguishable, field by
     * field, from a business with nothing.
     *
     * ⚠️ **AN EMPTY LIST MEANS "UNSTATED", NEVER "NOTHING WAS ASKED".** A
     * summary built by hand — a fixture, a future non-Google source — has made
     * no claim about its provenance, so every question here answers false and
     * every inference built on it declines to conclude. Fail-closed is the only
     * safe default for a value that ends up in a public sentence.
     */
    public function askedFor(string $field): bool
    {
        return in_array($field, $this->requestedFields, true);
    }

    /**
     * The number of Google reviews this listing has, or null when we cannot say.
     *
     * ⛔ **GOOGLE OMITS A FIELD THAT HOLDS ITS DEFAULT VALUE, EVEN WHEN THE MASK
     * ASKED FOR IT.** Places API (New), "Choose fields to return", read live on
     * 2026-08-26 (page last updated 2026-08-25 UTC), verbatim: *"When a response
     * message is parsed, and a field in the response message contains its
     * default value, the field may be omitted from the response even if you
     * specified it in the response field mask."* So for this vendor an **absent
     * `userRatingCount` is most often a genuine zero** — the business with no
     * reviews is precisely the business whose field is missing.
     *
     * ⛔ **WHICH IS WHY "ABSENT MEANS UNKNOWN" IS THE WRONG REPAIR.** It reads
     * as the careful answer and it would silence *"You have no Google reviews"*
     * for the exact business the free audit exists to find. The repair is
     * **corroboration**, and it is free because the fields it reads were already
     * paid for in the same response: a zero rating is omitted by the same rule,
     * so `rating` absent means the listing has no ratings, which means the count
     * is zero. `reviews` non-empty proves the opposite outright.
     *
     * The four answers, in order:
     *
     *   - a number Google sent — including an explicit `0` — is the number;
     *   - reviews came back, so the count is not zero and we do not know it;
     *   - the mask did not ask for both `userRatingCount` and `rating`, so there
     *     is nothing to corroborate with and no conclusion is available;
     *   - a rating came back, so the listing is rated and the missing count is a
     *     response that contradicts itself. **This is the case `?? 0` published
     *     as "you have no Google reviews" on an unauthenticated page.**
     *
     * ⚠️ **NULL IS NOT A COUNT AND MUST NEVER BE DEFAULTED BY A CALLER.** The
     * whole of this method is undone by one `?? 0` at a call site, which is the
     * shape it was written to remove.
     */
    public function knownReviewCount(): ?int
    {
        if ($this->userRatingCount !== null) {
            return $this->userRatingCount;
        }

        if ($this->reviews !== []) {
            return null;
        }

        if (! $this->askedFor('userRatingCount') || ! $this->askedFor('rating')) {
            return null;
        }

        return $this->rating === null ? 0 : null;
    }

    /**
     * Where {@see self::knownReviewCount()}'s answer came from, or null when it
     * had none.
     *
     * ⚠️ **THE TWO STATES STOP SHARING ONE VALUE EVEN WHERE THEY SHARE A
     * SENTENCE.** A reported zero and a corroborated zero produce the same
     * finding for the business — correctly, because both mean the same thing to
     * them — but they are not the same fact about *our* reading of the vendor,
     * and the audit record is the only place that difference could ever be
     * recovered from. A support conversation six weeks later has nothing else
     * to go on: the Places cache is long gone and nothing else logs a response
     * body (VendorLog logs the call shape, never the payload).
     */
    public function reviewCountSource(): ?string
    {
        if ($this->knownReviewCount() === null) {
            return null;
        }

        return $this->userRatingCount !== null ? 'reported' : 'inferred';
    }

    /**
     * The share of fetched reviews carrying an owner reply, or null when there
     * is nothing to divide by.
     *
     * Null rather than 0.0 on an empty set, because "no reviews" and "no replies
     * to any review" are different findings and the second one is an accusation.
     */
    public function replyRate(): ?float
    {
        if ($this->reviews === []) {
            return null;
        }

        $replied = count(array_filter(
            $this->reviews,
            static fn (array $review): bool => $review['hasOwnerReply'],
        ));

        return $replied / count($this->reviews);
    }

    public function hasDescription(): bool
    {
        return $this->editorialSummary !== null && trim($this->editorialSummary) !== '';
    }

    /**
     * How many photos this listing has on Google, or null when we cannot say.
     *
     * ⛔ **`$photoCount` IS TYPED `int` = `0`, SO IT COLLAPSES *"NOBODY ASKED"*
     * INTO *"THERE ARE NONE"* AT THE DTO ITSELF.** `GbpCompletenessCheck` turns
     * a zero straight into `gbp.photos_none`, an owner-facing finding — so a
     * summary built from a call that never requested `photos` (Text Search's
     * mask does not) would report a real business as having no photos on the
     * strength of our own request headers, exactly the failure
     * {@see self::askedFor()}'s docblock names.
     *
     * ⚠️ **NO SECOND FIELD TO CORROBORATE AGAINST, AND NONE IS NEEDED.**
     * `knownReviewCount()` corroborates a missing `userRatingCount` against
     * `rating` because two independent scalar fields can each hold a non-default
     * value, so one being missing while the other is present is a genuine
     * contradiction worth distrusting. `photos` is a single repeated field —
     * Google's own default-value-omission rule (`PlaceSummary::knownReviewCount()`'s
     * docblock has the citation and the date) applies to it exactly the same
     * way, and there is nothing else in this response that could contradict an
     * empty photo list. So the only question worth asking is the one
     * {@see self::askedFor()} already answers: did the mask that produced this
     * summary request `photos` at all. If it did, an absent list is a genuine
     * zero — **the corroboration is the mask itself**, already paid for and
     * already on this object — and refusing to report it would silence the
     * exact business the free audit exists to find. If it did not, there is
     * nothing to conclude.
     */
    public function knownPhotoCount(): ?int
    {
        return $this->askedFor('photos') ? $this->photoCount : null;
    }

    /**
     * Whether this listing has opening hours on Google, or null when we cannot
     * say.
     *
     * The same shape as {@see self::knownPhotoCount()}, for `regularOpeningHours`
     * rather than `photos`: `$hasOpeningHours` is typed `bool` = `false`, which
     * collapses *"nobody asked"* into *"there are none"* at the DTO, and
     * `GbpCompletenessCheck` turns that straight into `gbp.hours_missing`. A
     * message-typed field's "default value" under Google's omission rule is
     * simply *unset* — there is no separate scalar to corroborate against, and
     * none is needed for the same reason a repeated field needs none: the mask
     * itself is the only fact in question.
     */
    public function knownHasOpeningHours(): ?bool
    {
        return $this->askedFor('regularOpeningHours') ? $this->hasOpeningHours : null;
    }

    /**
     * The listing's categories, primary first and without duplicates.
     *
     * Google returns `primaryType` separately from `types`, and `types` usually
     * contains the primary one again along with generic noise like
     * `establishment` and `point_of_interest`. Ordering matters to the consumer:
     * slice H's wizard shows the first entry, so "dentist" must not end up
     * behind "point_of_interest" because of how the API happened to sort them.
     *
     * ⚠️ SHARED RATHER THAN COPIED, AND THE SECOND CONSUMER IS WHY IT MOVED HERE.
     * This was private on `AuditEngine`, where it produced `public_audits.
     * categories` — the list `TenantClassification::forCategories()` reads at
     * provisioning. The wizard's confirmation path now feeds that same decider
     * from a `PlaceCandidate`, and a second copy of this normalisation is where
     * the two paths would quietly stop agreeing: one of them dropping the primary
     * type, or ordering it behind the noise, changes what a classifier sees
     * without changing anything a reader would look at. Decision 197's argument
     * ("the SSRF allowlist and the confirm-then-persist contract must not
     * diverge") at the scale of one array.
     *
     * A pure function over this object's own two fields, which is the only
     * reason it can live on the DTO rather than in a service.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        $categories = $this->primaryType === null
            ? $this->types
            : [$this->primaryType, ...$this->types];

        return array_values(array_unique($categories));
    }
}
