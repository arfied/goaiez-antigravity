<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\GrowthPageStatus;
use App\Enums\GrowthPageType;
use App\Exceptions\GrowthPageRefused;
use App\Exceptions\TenantMismatch;
use App\Models\GrowthPage;
use App\Models\Location;
use App\Support\Tenancy;
use Generator;
use Illuminate\Support\Carbon;

/**
 * The one way a page gets onto — or held off — a tenant's website.
 *
 * ⛔ **THE ONLY WRITER OF `growth_pages`**, held by a chokepoint lint in
 * `tests/Feature/Architecture/ContentTest.php` on `SiteChanges`' precedent
 * (5521). What it protects is one pairing: **`status` and `hold_until` together
 * decide whether a page publishes**, and {@see self::holdUntil()} is the only
 * thing that refuses to put a release time on a page the gate turned down. A
 * second writer is a second way that refusal is skipped, and the page it lets
 * through is one this platform already decided should not exist.
 *
 * ## The sequence
 *
 *   1. {@see self::draft()} records a candidate. It is `draft`, ungated, and
 *      goes nowhere.
 *   2. {@see self::gate()} judges it against `16` §15.3 and either leaves it
 *      `draft` or holds it, with the reasons on a `content_quality_checks` row.
 *   3. Slice D publishes what cleared, after AUTO-WITH-HOLD.
 *
 * ⚠️ **NOTHING IN `app/` CALLS `draft()` OR `holdUntil()` YET, AND THAT IS 272's
 * SHAPE SAID OUT LOUD RATHER THAN DISCOVERED LATER** (5570). Slice D is their
 * caller: it dispatches `PublishGrowthPageJob`, which drafts from the candidate
 * it was given, gates it, and holds it for twenty-four hours. The alternative
 * was to ship a table whose only writer was a factory, which is precisely
 * `businesses.pixel_tenant_id` (4961) — *"every factory-built test would have
 * exercised a collector against a key no real tenant had"*. **A writer with no
 * caller is visible; a factory pretending to be a writer is not.**
 *
 * ⛔ **THERE IS NO GENERATOR HERE AND ONE MUST NOT BE ADDED** (`BUILD-PLAN`
 * §2.11.5 conflict 5). The content engine and the Orchestrator's DIAGNOSE are
 * Stage 5; building one to satisfy a gate verb absorbs row 12.
 */
final class GrowthPages
{
    public function __construct(
        private readonly ContentQuality $quality,
    ) {}

    /**
     * Record a candidate page. Nothing is judged and nothing is published.
     */
    public function draft(Location $location, GrowthPageDraft $draft): GrowthPage
    {
        $this->assertSameTenant($location);

        if (trim($draft->slug) === '') {
            throw GrowthPageRefused::withoutSlug();
        }

        $page = new GrowthPage;

        $page->location_id = $location->id;
        $page->type = $draft->type;
        $page->slug = trim($draft->slug);
        $page->title = $draft->copy->title;
        $page->meta_description = $draft->copy->metaDescription;
        $page->content = $draft->copy->content;
        $page->target_keyword = $draft->targetKeyword;
        $page->status = GrowthPageStatus::Draft;
        $page->hold_until = null;
        $page->save();

        return $page;
    }

    /**
     * Run the publish gate over a page and record what it decided.
     *
     * ⛔ **THE GATE IS WHAT REFUSES, AND NOTHING UPSTREAM OF IT IS ALLOWED TO
     * REFUSE FIRST** (398). There is no toggle, no cap and no kill switch in
     * front of this call — slice D adds `actuation.enabled`, `paused_at` and the
     * volume caps at the *job*, above here — so a test that drives a thin page
     * into this method with everything else green is driving the gate itself.
     *
     * ⚠️ **NOT WRAPPED IN A TRANSACTION, ON `SiteChanges::apply()`'s
     * REASONING.** {@see ContentQuality::assess()} makes a model call, and
     * holding a database transaction open across a vendor round trip is how a
     * slow provider becomes a lock-wait outage. The two writes are a check row
     * and a status; a crash between them leaves a page that reads as ungated,
     * which the next run corrects by gating it again.
     */
    public function gate(GrowthPage $page, ContentEvidence $evidence): QualityOutcome
    {
        $this->assertSameTenant($page->location);

        if ($page->status->isTerminal()) {
            throw GrowthPageRefused::alreadyPublished((int) $page->id);
        }

        $outcome = $this->quality->assess(
            (int) $page->id,
            new PageCopy($page->title, $page->meta_description, $page->content),
            $this->otherPages($page),
            $evidence,
        );

        $page->quality_score = $outcome->qualityScore;

        // ⛔ **A HELD PAGE LOSES ANY RELEASE TIME IT HAD.** Re-gating a page that
        // slice D had put on a twenty-four hour hold, and finding it fails, must
        // not leave the clock running — silence is not consent to publish
        // something we have just refused.
        $page->status = $outcome->holds() ? GrowthPageStatus::Held : GrowthPageStatus::Draft;
        $page->hold_until = null;

        $page->save();

        return $outcome;
    }

    /**
     * Put a page on a hold that lapses — slice D's AUTO-WITH-HOLD.
     *
     * ⛔ **REFUSES A PAGE THAT DID NOT CLEAR THE GATE, AND THAT REFUSAL IS THE
     * REASON THIS METHOD EXISTS RATHER THAN AN ASSIGNMENT AT THE CALL SITE**
     * (5565). `status = held` covers two different holds: one that waits for a
     * person and one that proceeds on silence. If a gate failure could acquire a
     * release time, the lapsed-hold sweep would publish the exact page the gate
     * refused — and the suite would stay green, because both rows look
     * identical.
     *
     * ⚠️ **A HOLD IN THE PAST IS REFUSED TOO.** `29` requires AUTO-WITH-HOLD to
     * proceed on silence *after* a window in which the owner could have spoken;
     * a window that has already closed is not a hold, it is a publication.
     */
    public function holdUntil(GrowthPage $page, Carbon $until): void
    {
        $this->assertSameTenant($page->location);

        if ($page->status->isTerminal()) {
            throw GrowthPageRefused::alreadyPublished((int) $page->id);
        }

        if (! $this->quality->clearedTheGate((int) $page->id)) {
            throw GrowthPageRefused::withoutClearingTheGate((int) $page->id);
        }

        if (! $until->isFuture()) {
            throw GrowthPageRefused::withAPastHold((int) $page->id);
        }

        $page->status = GrowthPageStatus::Held;
        $page->hold_until = $until;
        $page->save();
    }

    /**
     * One page, as everything outside this class may see it.
     *
     * ⛔ **THE PIPELINE READS THROUGH HERE BECAUSE IT MAY NOT READ THE MODEL**
     * (5661). The chokepoint lint refuses the *import* of `App\Models\GrowthPage`
     * anywhere but this file, so a publisher, a job or a screen that wanted the
     * row would either break the lint or reach the table another way. Handing
     * back a value object is the answer {@see ContentQuality::assess()} already
     * gave to the same constraint — it takes an `int $pageId` for exactly this
     * reason — rather than widening the allowlist until it caught nothing (511).
     *
     * ⚠️ **NULL MEANS "NOT THIS TENANT'S", AND THAT IS THE GLOBAL SCOPE AND RLS
     * ANSWERING RATHER THAN A COMPARISON HERE.** Another tenant's page id finds
     * nothing at either layer, so there is no id-to-tenant check to forget.
     */
    public function candidate(int $pageId): ?PublishCandidate
    {
        $page = GrowthPage::query()->find($pageId);

        return $page instanceof GrowthPage ? $this->candidateOf($page) : null;
    }

    /**
     * Whether the gate has already said yes about this page.
     *
     * ⚠️ **A PASSTHROUGH, AND IT EXISTS SO THAT THE PIPELINE DOES NOT HAVE TO
     * TAKE `ContentQuality` AS A DEPENDENCY OF ITS OWN.** The verdict lives on a
     * `content_quality_checks` row rather than on `growth_pages` (345's rule),
     * so *"may this publish?"* has exactly one answer and no ordering can put
     * two of them out of step.
     */
    public function hasClearedTheGate(int $pageId): bool
    {
        return $this->quality->clearedTheGate($pageId);
    }

    /**
     * {@see self::gate()}, for a caller that has an id rather than a row.
     */
    public function gateCandidate(int $pageId, ContentEvidence $evidence): QualityOutcome
    {
        $page = GrowthPage::query()->find($pageId);

        if (! $page instanceof GrowthPage) {
            throw GrowthPageRefused::missing($pageId);
        }

        return $this->gate($page, $evidence);
    }

    /**
     * {@see self::holdUntil()}, for a caller that has an id rather than a row.
     */
    public function holdCandidate(int $pageId, Carbon $until): void
    {
        $page = GrowthPage::query()->find($pageId);

        if (! $page instanceof GrowthPage) {
            throw GrowthPageRefused::missing($pageId);
        }

        $this->holdUntil($page, $until);
    }

    /**
     * The owner said STOP. The hold stops lapsing and starts waiting for them.
     *
     * ⛔ **THIS IS THE OTHER HALF OF AUTO-WITH-HOLD AND IT IS WHY THE FIRST HALF
     * IS DEFENSIBLE** (`29` §4.5). *"Proceeds unless they reply STOP within the
     * window"* — silence is only consent when the owner had a way to break it,
     * so a release-on-silence with no stop path is publishing to a stranger's
     * website on the strength of a question nobody was asked.
     *
     * ⚠️ **IT CLEARS `hold_until` AND LEAVES THE STATUS `held`**, which is
     * exactly 5565's distinction read the other way: the page now waits for a
     * person, and the lapsed-hold sweep can never pick it up again. Nothing
     * republishes it without somebody deciding to.
     *
     * ⚠️ **IDEMPOTENT ON PURPOSE.** The notice is an email and email clients
     * fetch links more than once; a second STOP on a page already waiting for a
     * person is a no-op rather than an error page for an owner who did the right
     * thing twice.
     */
    public function holdForOwner(int $pageId): bool
    {
        $page = GrowthPage::query()->find($pageId);

        if (! $page instanceof GrowthPage) {
            throw GrowthPageRefused::missing($pageId);
        }

        $this->assertSameTenant($page->location);

        if ($page->status->isTerminal()) {
            throw GrowthPageRefused::alreadyPublished((int) $page->id);
        }

        if (! $page->releasesOnSilence()) {
            return false;
        }

        $page->status = GrowthPageStatus::Held;
        $page->hold_until = null;
        $page->save();

        return true;
    }

    /**
     * The page is on the tenant's website. Say so, once.
     *
     * ⛔ **REFUSES A PAGE THAT DID NOT CLEAR THE GATE, ON `holdUntil()`'s
     * ARGUMENT** (5565). This is the last writer in the chain and the only one
     * that makes the state terminal, so a caller that skipped the gate — a
     * future screen, a Stage 5 generator, a retry that lost its outcome — must
     * not be able to reach `published` through it. The check is here rather than
     * at the call site because the call site is the thing that changes.
     *
     * ⚠️ **`published_at` AND `status` MOVE TOGETHER OR NEITHER DOES**, held by
     * a CHECK constraint as well as by this method: slice H anchors a fourteen
     * day window on that timestamp, and a `published` row without one cannot be
     * measured or rolled back on a schedule.
     */
    public function markPublished(int $pageId): PublishCandidate
    {
        $page = GrowthPage::query()->find($pageId);

        if (! $page instanceof GrowthPage) {
            throw GrowthPageRefused::missing($pageId);
        }

        $this->assertSameTenant($page->location);

        if ($page->status->isTerminal()) {
            throw GrowthPageRefused::alreadyPublished((int) $page->id);
        }

        if (! $this->quality->clearedTheGate((int) $page->id)) {
            throw GrowthPageRefused::withoutClearingTheGate((int) $page->id);
        }

        $page->status = GrowthPageStatus::Published;
        $page->hold_until = null;
        $page->published_at = Carbon::now();
        $page->save();

        return $this->candidateOf($page);
    }

    /**
     * The pages whose AUTO-WITH-HOLD window has closed.
     *
     * ⛔ **`hold_until IS NOT NULL` IS PART OF THE QUESTION AND NOT AN
     * OPTIMISATION** (5565). A held page with no release time waits for a
     * person — the gate's own hold, and the state {@see self::holdForOwner()}
     * puts a page into — and `hold_until <= now` on a null column is simply
     * false in SQL, so the predicate is written out rather than relied upon.
     *
     * ⚠️ **IDS RATHER THAN ROWS, AND ORDERED, SO THE SWEEP IS RESUMABLE.**
     *
     * @return list<int>
     */
    public function dueForRelease(Carbon $now, int $limit = 200): array
    {
        $ids = GrowthPage::query()
            ->where('status', GrowthPageStatus::Held->value)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<=', $now)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }

    /**
     * How many pages of one kind this location has published since a moment.
     *
     * ⛔ **THE VOLUME CAP IS THIS COUNT AND NOTHING ELSE** — no column, no flag,
     * no stored pause. A cap that is derived releases itself at the period
     * boundary and cannot drift from the rows that justify it; a stored one is
     * `site_changes.tier` and `messaging_lane`'s argument in miniature.
     *
     * ⚠️ **`published_at`, NEVER `created_at`.** A page drafted in March, held,
     * and published in April spends April's allowance — the cap is about what
     * appeared on the website, which is the thing Google counts.
     *
     * @param  bool  $posts  Blog posts, which `16` §15.3 caps on their own line.
     */
    public function publishedSince(int $locationId, Carbon $since, bool $posts): int
    {
        $query = GrowthPage::query()
            ->where('location_id', $locationId)
            ->where('status', GrowthPageStatus::Published->value)
            ->where('published_at', '>=', $since);

        return $posts
            ? $query->where('type', GrowthPageType::Post->value)->count()
            : $query->where('type', '!=', GrowthPageType::Post->value)->count();
    }

    /**
     * Every page this location has actually published, for the daily self-audit.
     *
     * ⚠️ **PUBLISHED ONLY.** A draft that never went anywhere cannot have zero
     * impressions in a way anybody should be told about, and two drafts that
     * resemble each other are the gate's business rather than the audit's.
     *
     * @return list<PublishCandidate>
     */
    public function publishedAt(int $locationId): array
    {
        $pages = GrowthPage::query()
            ->where('location_id', $locationId)
            ->where('status', GrowthPageStatus::Published->value)
            ->orderBy('id')
            ->get()
            ->all();

        return array_values(array_map(
            fn (GrowthPage $page): PublishCandidate => $this->candidateOf($page),
            $pages,
        ));
    }

    private function candidateOf(GrowthPage $page): PublishCandidate
    {
        return new PublishCandidate(
            (int) $page->id,
            (int) $page->location_id,
            $page->type,
            $page->slug,
            new PageCopy($page->title, $page->meta_description, $page->content),
            $page->target_keyword,
            $page->status,
            $page->hold_until,
            $page->published_at,
        );
    }

    /**
     * Every other page this tenant has, as text.
     *
     * ⛔ **THE TENANT'S PAGES, WHICH IS STRICTER THAN `16`'s WORDING AND
     * DELIBERATELY SO** (5567). `16` §15.3 says *"vs. every other page on the
     * site"*; `BUILD-PLAN` §2.11.3 says *"against the tenant's other pages"*, and
     * the tenant is the right boundary because the failure `16` §15.1 actually
     * warns about is the city×service matrix — *"across hundreds of locations
     * where only the city name changes"* — which a per-location comparison
     * cannot see at all.
     *
     * ⚠️ **IT CANNOT SEE ANOTHER TENANT'S PAGES AND MUST NOT.** Two businesses
     * two streets apart will write similar pages about the same trade, and
     * scoring one down for the other's writing would refuse an honest page for a
     * reason its owner could never be shown. The global scope and row-level
     * security both hold that line; the isolation test drives both.
     *
     * ⚠️ **`cursor()` RATHER THAN `get()`**: a tenant's whole corpus is the one
     * unbounded read in this slice, and `16`'s own caps put it in the hundreds
     * rather than the millions — but the cap is slice D's and this runs before
     * it exists.
     *
     * @return Generator<int, string>
     */
    private function otherPages(GrowthPage $page): Generator
    {
        /** @var GrowthPage $other */
        foreach (GrowthPage::query()->whereKeyNot($page->getKey())->cursor() as $other) {
            yield (new PageCopy($other->title, $other->meta_description, $other->content))->fullText();
        }
    }

    /**
     * ⚠️ **`SiteChanges::assertSameTenant()`'s ARGUMENT, VERBATIM.** A loaded
     * `Location` is self-scoping; an unsaved one is not loaded at all, and if
     * this service took its `business_id` as authorization the argument would
     * have become the boundary.
     */
    private function assertSameTenant(Location $location): void
    {
        $tenant = Tenancy::idOrFail();

        if ($location->business_id !== $tenant) {
            throw new TenantMismatch($tenant, $location->business_id);
        }
    }
}
