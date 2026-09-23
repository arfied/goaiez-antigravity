<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackPage;
use App\Models\Location;
use App\Services\Config\DefaultsRegistry;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only reader and writer of `feedback_pages`.
 *
 * THE SLUG IS IMMUTABLE ONCE MINTED, and that is a decision with a visible cost
 * rather than a convenience. A printed QR sign and — under `24` 3.1 — a
 * carrier-submitted 10DLC opt-in URL both depend on the address resolving
 * forever. So a business later renamed to Joe's Dental keeps whatever slug was
 * minted at signup. Renaming with a permanent redirect is a later row's
 * feature. Do not add a re-mint path here without one.
 *
 * A VISITOR WHO SIGNS UP AT /start RATHER THAN THROUGH THE AUDIT GETS A
 * STEM-LESS SLUG, MINTED FROM ENTROPY ALONE (decision 334, correcting 325).
 * TenantProvisioner::fallbackName() names that location after the *person*
 * signing up, not a verified business name — and this slug is public,
 * permanent, and printed on physical signage. Minting from a personal name
 * would put it there too. `provisionFor()`'s `$nameIsPersonal` flag is how the
 * caller — the only place that knows whether the name came from a verified
 * audit snapshot or from the fallback — tells this class which case it is.
 *
 * WHY A RANDOM SUFFIX RATHER THAN A COLLISION COUNTER. Two reasons that point
 * the same way. A readable-only slug makes the whole platform's tenant list
 * enumerable by guessing business names, and a collision counter hands the
 * second Joe's Dental a visibly worse URL than the first for a reason they
 * cannot see. Four characters of entropy fixes both, and readability was never
 * what carried the trust — the business name in the page's own heading is.
 */
final class FeedbackPages
{
    /**
     * Four characters of lowercase base36 — about 1.7 million combinations per
     * name stem, which is far more than enough to make guessing pointless while
     * keeping the URL short enough to read aloud.
     */
    public const int SUFFIX_LENGTH = 4;

    /**
     * Long enough for a real business name, short enough that the whole slug
     * fits on a printed sign and in a text message without wrapping.
     */
    public const int MAX_NAME_LENGTH = 40;

    public function maxNameLength(): int
    {
        return app(DefaultsRegistry::class)->int('feedback.pages.max_name_length');
    }

    /**
     * Mint this location's page, or return the one it already has.
     *
     * IDEMPOTENT UNDER CONCURRENCY, not merely on repeated calls — that is the
     * guarantee both of this method's callers actually need. The registration
     * path calls this from inside the signup transaction, where a second
     * request for the same user could plausibly race it; a future backfill
     * would call this in a loop from a console command, where two workers could
     * pick up the same location. Either way, a caller that loses the race must
     * get the winner's row back, not an exception — that is what makes the
     * missing backfill affordable in the first place: the creating migration
     * explains why a migration cannot enumerate locations at all, and this
     * method is what a tenant-aware console command would loop over on the day
     * one is needed, without adding its own locking.
     *
     * THE INSERT RUNS IN ITS OWN TRANSACTION, DELIBERATELY NOT WRAPPING THE
     * READ ABOVE IT. Postgres aborts a whole transaction on any error, so
     * catching the unique-violation *inside* the same transaction that ran the
     * failing insert would leave the connection unable to run the re-select
     * that follows — the same 25P02 hazard Tenancy::applyToDatabase()
     * documents. Scoping DB::transaction() to just the insert means a
     * unique-violation there rolls back only that attempt (as its own
     * transaction, or as a SAVEPOINT if this method is itself already running
     * inside the registration transaction — Laravel nests automatically), and
     * the connection is clean by the time the catch block re-queries.
     *
     * $nameIsPersonal MUST BE TRUE WHENEVER $location->name IS NOT A VERIFIED
     * BUSINESS NAME. TenantProvisioner is the only caller and the only place
     * that knows which case it minted the name from — a Google-sourced audit
     * snapshot, or `fallbackName()`'s copy of the signing-up person's own
     * name. Defaulting to `false` means a caller that forgets the flag gets
     * today's behaviour (a readable stem), which is the direction a mistake
     * here should fail: a business's own name in its own public URL is not a
     * privacy incident, so silence favours the case that already ships.
     */
    public function provisionFor(Location $location, bool $nameIsPersonal = false): FeedbackPage
    {
        $this->assertBelongsToTenant($location);

        $existing = FeedbackPage::query()
            ->where('location_id', $location->id)
            ->first();

        if ($existing instanceof FeedbackPage) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): FeedbackPage => FeedbackPage::query()->create([
                'business_id' => $location->business_id,
                'location_id' => $location->id,
                'slug' => $this->mintSlug($location, $nameIsPersonal),
                'is_published' => true,
            ]));
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            // Lost the race: another caller created this location's page
            // between the check above and this insert. Its row is what
            // provisionFor() promises — the same shape as
            // ConsentService::suppress()'s firstOrCreate(), where "a repeat is
            // the ordinary case, not an error".
            return FeedbackPage::query()->where('location_id', $location->id)->firstOrFail();
        }
    }

    /**
     * The page behind a public slug, or null.
     *
     * CALLED WITH NO TENANT ESTABLISHED, on purpose — this is the query whose
     * answer establishes it. The `public_read` policy is what permits it, and
     * the model carries no global scope for the same reason.
     *
     * Unpublished and unknown are the same answer. The caller turns both into
     * the same 404, so nothing here needs to distinguish them and nothing
     * downstream can accidentally report which it was.
     */
    public function resolve(string $slug): ?FeedbackPage
    {
        if (trim($slug) === '') {
            return null;
        }

        return FeedbackPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    /**
     * The page belonging to one of this tenant's locations.
     *
     * ⚠️ **THIS EXISTS BECAUSE THE CHOKEPOINT LINT REFUSED THE CALLER, AND THAT
     * IS THE SYSTEM WORKING.** `SendReviewInviteJob` needs a page to build the
     * destination links from, and reached for `FeedbackPage::query()` — which
     * *"nothing outside the feedback service touches the slug directory"* caught
     * on the first run. Decision 624's rule: when a lint refuses a new reader,
     * the question is whether the reader belongs behind the service, and the
     * answer has now been yes three times.
     *
     * ⚠️ **It is tenant-scoped by the caller, not by the model** — `FeedbackPage`
     * deliberately carries no global scope, because it is what *establishes* the
     * tenant from a public slug (318, 401). So this filters on a `location_id`
     * the caller already resolved under a tenant, and an unscoped caller would
     * get a page it had no business seeing. Every caller today is inside
     * `AutopilotJob`, which establishes one before `handle()` runs.
     *
     * Null when a location has no page — ordinary for a location provisioned
     * before slice C, and the reason the job treats it as "nothing to do".
     */
    public function forLocation(Location $location): ?FeedbackPage
    {
        return FeedbackPage::query()
            ->where('location_id', $location->id)
            ->first();
    }

    /**
     * A readable stem plus entropy, unique on the whole table.
     *
     * The loop is a belt-and-braces guard on a collision that is already
     * unlikely; the unique index is what actually guarantees correctness, and a
     * caller hitting the ceiling gets a slug of pure entropy rather than an
     * exception, because failing signup over a cosmetic URL would be the wrong
     * trade.
     *
     * THE TRUNCATION IS RE-TRIMMED, because Str::limit() cuts by character
     * count with no boundary awareness. A name whose 41st slugified character
     * is a hyphen would otherwise leave the stem ending in one, producing
     * `...a--wxyz` once the suffix is appended — and this slug is immutable
     * once minted (see the class docblock), so that is not a cosmetic defect
     * somebody fixes later. It is permanent, on a printed sign.
     *
     * $stemless SKIPS THE NAME ENTIRELY, rather than slugifying it and
     * discarding the result — a personal name never reaches Str::slug() at
     * all, so there is no path here where it is computed and then thrown
     * away. See the class docblock and decision 334.
     */
    private function mintSlug(Location $location, bool $stemless = false): string
    {
        $stem = $stemless ? '' : rtrim(Str::limit(Str::slug($location->name), $this->maxNameLength(), ''), '-');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $suffix = Str::lower(Str::random(self::SUFFIX_LENGTH));

            $candidate = $stem === '' ? $suffix : $stem.'-'.$suffix;

            if (! FeedbackPage::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        return Str::lower(Str::random(16));
    }

    /**
     * Refuse to mint a page for somebody else's location.
     *
     * THE ONLY LAYER THAT CATCHES A MISMATCHED business_id/location_id PAIR.
     * The `tenant_write` policy on `feedback_pages` compares only the row's own
     * `business_id` against the session tenant — nothing in SQL verifies that
     * `location_id` actually belongs to that `business_id`, because the policy
     * has no way to reach across to `locations` for that check. So a caller
     * holding a valid session for business A could otherwise insert
     * `business_id = A` with a `location_id` pointing at business B's location,
     * and the row-level security policy would let it through. This method is
     * what closes that gap: it compares `$location->business_id` against
     * `Tenancy::idOrFail()` before anything is written, and `provisionFor()`
     * takes `business_id` from `$location->business_id` — never from
     * `Tenancy::id()` independently — so the two cannot disagree with each
     * other while this check passes.
     *
     * The same reasoning ConsentService::assertBelongsToTenant() and
     * DestinationSettings::assertBelongsToTenant() give: the two ids are both
     * in hand here, so the caller can be told what is wrong rather than being
     * handed a SQLSTATE naming a policy.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A feedback page is minted against '
            .'the acting business, so this would publish a public address pointing into a '
            .'business that never asked for one.',
        );
    }

    /**
     * Whether this failure is Postgres reporting `unique_violation` (23505),
     * rather than something provisionFor() must not swallow — a lost
     * connection, a permissions error, or an RLS refusal, each of which is a
     * real failure with nothing to fall back to.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return SqlState::of($e) === '23505';
    }
}
