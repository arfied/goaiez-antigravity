<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Enums\PixelCollectionState;
use App\Services\Warehouse\PixelArrivals;
use App\Services\Widgets\WidgetPlugins;
use Carbon\CarbonImmutable;

/**
 * Whether this tenant's pixel is collecting anything, answered for the tenant
 * (7800–7802).
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE DEFECT THIS CLASS EXISTS FOR
 * ---------------------------------------------------------------------------
 * Every freshly provisioned tenant collects **nothing**. `WidgetPlugins::
 * provisionFor()` writes `allowed_domains => []`, `originIsAllowed()` reads an
 * empty list as *serve nowhere* — correctly, that is the direction a
 * cross-origin allowlist has to fail — and `PixelCollector` answers §11 row 2
 * from that same list (4968). The refusal is then invisible from **every** angle
 * a person would look from: `PixelIngestController` returns `204` on every path
 * by §11's transport rule, so the browser's network tab shows success; the
 * install screen said *"there is nothing else to do"*; and the precondition is
 * edited on a different screen, whose own copy is entirely about reviews.
 *
 * ⛔ **AND THE PLATFORM ALREADY HELD THE DIAGNOSIS.** `IngestRejects::record()`
 * rings `OperatorAlertKind::PixelIngestRejects`, whose summary reads *"A
 * tenant's website is not listed, so their pixel is collecting nothing"* — to
 * an operator. This class is that same answer, given to the only person who can
 * act on it.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHY THIS IS BUILDABLE ON AN ACCOUNT SCREEN AND AN OPS ONE IS HARD
 * ---------------------------------------------------------------------------
 * `ingest_rejects` is `ENABLE`+`FORCE` row-level secured on `app.business_id`,
 * so every read below is scoped by the acting tenant's own session without a
 * `where` being written. 7634(a)'s Ops screen has the opposite problem — no
 * admin screen in this codebase renders a cross-tenant list from an RLS'd table
 * (569) — and it stays open.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHAT THIS COULD NOT SEE, AND THE FIRST ENTRY WAS WRONG — CORRECTED
 * 2026-08-22 (7980–7983)
 * ---------------------------------------------------------------------------
 * The list opened: *"**Acceptance.** Nothing writes a last-seen for admitted
 * pixel traffic, so silence here is *not* health."* ⛔ **Something does.**
 * [[\App\Jobs\ArchivePixelBatchJob]] writes the derived event layer inline in
 * the same job as the archive, and it has since twelve hours before the screen
 * that says otherwise existed. {@see PixelArrivals}
 * is the read, and this class asks it. ⚠️ **The clause after the comma survives
 * and is why the reader is asked for a *timestamp* rather than a boolean**:
 * silence is still not health, because the write is queued, a batch that fails
 * to decode derives nothing, the derived layer expires at four hundred days,
 * and a replay can empty a range. **What changed is that noise is now
 * health** — a non-null answer is proof, where before there was neither.
 *
 * ---------------------------------------------------------------------------
 * ⛔ AND THE SILENCE WAS BEING RENDERED AS A FACT ABOUT THE TENANT'S OWN
 * WEBSITE — CORRECTED 2026-08-26 (9900)
 * ---------------------------------------------------------------------------
 * The four reasons above are all reasons about **us**, and the sentence the
 * screen printed on top of them was about **them**: *"A website has to be
 * visited before there is anything to send."* ⛔ **`Storage::disk('s3')` could
 * not be built on any deployment between 2026-08-18 and 2026-08-25** (9408,
 * 9421), and [[\App\Jobs\ArchivePixelBatchJob]] archives before it derives, so
 * for that week every owner with a correct install and a busy website was told
 * nobody had come. {@see PixelAcceptances} is the third read this class makes
 * and the only one taken from the **admission** side of the collector, which is
 * what tells the two apart.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHAT THIS STILL CANNOT SEE, SAID BEFORE ANY SCREEN QUOTES IT (352, 397)
 * ---------------------------------------------------------------------------
 *  - **Every other refusal.** `IngestRejects::record()` has exactly one call
 *    site, on `PixelRefusal::OriginNotAllowed`, and that enum has eight cases —
 *    a batch dropped at the monthly cap, refused for a form value, or refused
 *    for a `Phi` tenant appears nowhere below. The copy therefore names the one
 *    reason it can prove rather than claiming to list every reason a batch was
 *    turned away.
 *  - **Who called.** A refusal is evidence that *something* posted this
 *    tenant's public key claiming that origin. The key ships in the page and
 *    `Origin` is trivially forged by anything that is not a browser, so this is
 *    evidence rather than proof — `WidgetInstalls`' own limit (3099), and no
 *    sentence on the screen may promote it.
 */
final class PixelCollections
{
    /**
     * How many refused addresses a tenant is shown.
     *
     * ⚠️ **A RENDERING BOUND, NOT A DIAGNOSTIC ONE.** The honest case produces
     * one address — 4968's mis-installed tenant, or a `www` the owner forgot —
     * and a handful at the outside. The unbounded case is adversarial:
     * {@see IngestRejects::ORIGINS_PER_HOUR} folds a tenant's distinct origins
     * past fifty an hour into one bucket (7710), which still leaves hundreds of
     * *named* addresses inside a seven-day window if somebody is deliberately
     * spending a public key. Five is more than enough to act on and the count
     * beside it says what is not shown, so the page cannot become a list of a
     * stranger's strings.
     */
    public const int MAX_ADDRESSES_SHOWN = 5;

    public function __construct(
        private readonly WidgetPlugins $plugins,
        private readonly IngestRejects $rejects,
        private readonly PixelArrivals $arrivals,
        private readonly PixelAcceptances $acceptances,
    ) {}

    /**
     * The acting tenant's collection state.
     *
     * ⛔ **THIS METHOD OPENED WITH `Tenancy::idOrFail()` AND THE LINE WAS
     * DELETED BY MUTATION RATHER THAN BY TASTE** (7804, 5051's shape). The
     * docblock claimed it *"fails in this class's own name rather than inside a
     * global scope three frames down"* — and **the mutant survived**: every read
     * below goes through a tenant-scoped model, `TenantScope` calls
     * `Tenancy::idOrFail()` itself, and it throws the *same* `TenantNotResolved`
     * either way. So the guard was unfalsifiable and bought nothing but a stack
     * frame, which is 398 exactly: an inner check alibied by an outer one, with
     * a comment explaining why it was worth having.
     *
     * ⚠️ **THE FAIL-CLOSED PROPERTY IS REAL AND IS THE SCOPE'S**, and the test
     * naming it stays — it reddens the day somebody reaches for
     * `withoutGlobalScope()` on any of the reads below, which is the change that
     * would actually matter here: this is a reader over other people's traffic.
     * ⚠️ **THAT NOW COVERS TWO FURTHER READS, AND THE STRICTEST OF THEM ALL.**
     * {@see PixelArrivals} reads the derived event
     * layer, which is the visitors themselves rather than a count of refusals,
     * and it carries no guard of its own for exactly 7804's reason;
     * {@see PixelAcceptances} reads the monthly admission counter on the same
     * terms.
     */
    public function status(): PixelCollectionStatus
    {
        $listed = $this->plugins->acceptedHosts();

        [$shown, $others] = $this->refusals();

        // ⚠️ **READ ON EVERY PATH, INCLUDING THE ONES THAT CANNOT REACH
        // `Collecting`.** A tenant who has emptied their allowlist since traffic
        // last arrived is told the more urgent thing — nothing will arrive from
        // now on — and is still shown when the last visit landed, because that
        // is the fact that tells them what they have just switched off. Reading
        // it only on the arm that uses it would make the screen's reassurance a
        // function of which problem the tenant has.
        $lastArrivedAt = $this->arrivals->lastArrivedAt();

        if (! $this->plugins->businessHasAnyFeed()) {
            // ⛔ A DIFFERENT STATE FROM AN EMPTY LIST, AND THE ONE THING A
            // SCREEN MUST NOT DO IS SEND THIS TENANT SOMEWHERE TO TYPE A
            // WEBSITE — there is no box for them. See
            // `WidgetPlugins::businessHasAnyFeed()`.
            return new PixelCollectionStatus(PixelCollectionState::NotSetUpYet, $listed, $shown, $others, $lastArrivedAt);
        }

        if ($listed === []) {
            return new PixelCollectionStatus(PixelCollectionState::NoWebsiteListed, $listed, $shown, $others, $lastArrivedAt);
        }

        // ⚠️ **A REFUSAL ALONGSIDE A LISTED WEBSITE IS A DIFFERENT PROBLEM FROM
        // A REFUSAL WITH NONE**, and it is the near miss rather than the
        // missing step: one of this tenant's addresses is not the one written
        // down. Ordering the states this way is what keeps the more actionable
        // sentence in front of the less actionable one — an account with no
        // website listed is told *that*, not that some visits were refused,
        // because listing the website fixes both.
        // ⛔ **THE REFUSAL STILL OUTRANKS THE ARRIVAL, AND THE ORDER IS THE SAME
        // ARGUMENT THE PARAGRAPH ABOVE MAKES ABOUT THE EMPTY LIST** (7982): a
        // tenant collecting from `example.com` while `www.example.com` is turned
        // away is a tenant with something to fix, and a green tick over a
        // half-working install is the reassurance that stops them fixing it.
        // ⚠️ **NOTHING IS LOST BY THE ORDERING** — `$lastArrivedAt` rides on
        // every state and the screen renders it beside whichever one won.
        if ($shown !== []) {
            return new PixelCollectionStatus(PixelCollectionState::TrafficRefused, $listed, $shown, $others, $lastArrivedAt);
        }

        // ⛔ **THE ONE POSITIVE THIS APPLICATION CAN PROVE, AND IT IS A
        // TIMESTAMP RATHER THAN A GUESS.** Everything above is an inference
        // about a gate; this is a row that exists only because a batch of this
        // tenant's own visitors' events got through every gate and was kept.
        if ($lastArrivedAt instanceof CarbonImmutable) {
            return new PixelCollectionStatus(PixelCollectionState::Collecting, $listed, $shown, $others, $lastArrivedAt);
        }

        // ⛔ **AND THE NEGATIVE IS TWO DIFFERENT ANSWERS THAT WERE SHARING ONE
        // SENTENCE — 2026-08-26 (9900).** Silence in the derived layer used to
        // become *"a website has to be visited before there is anything to
        // send"*, which is a claim about the tenant's own visitors; the
        // archive that produces that silence is ours, and it produced it on
        // every deployment for a week (9408, 9421). {@see PixelAcceptances}
        // reads the counter the collector writes **before** it hands a batch
        // to the queue, so a tenant who got past every gate and has nothing to
        // show is told the truth about whose problem that is.
        //
        // ⚠️ **READ HERE RATHER THAN BESIDE `$lastArrivedAt`, WHICH IS THE
        // OPPOSITE OF THE DECISION ONE BLOCK UP, AND THE DIFFERENCE IS WHAT
        // EACH IS FOR.** The arrival is *rendered* on every state, so it is
        // read on every path; this decides between two states and is rendered
        // nowhere, so on every other arm it would be a query whose answer
        // changes nothing a person reads.
        return new PixelCollectionStatus(
            $this->acceptances->acceptedRecently()
                ? PixelCollectionState::AcceptedNotShown
                : PixelCollectionState::Listening,
            $listed,
            $shown,
            $others,
            $lastArrivedAt,
        );
    }

    /**
     * The refused addresses to show, busiest first, and how many are not shown.
     *
     * ⚠️ **SORTED IN PHP RATHER THAN IN SQL.** `ConventionsTest` fails the build
     * on a descending sort over anything but `id`, and the order a person wants
     * here is by count — which is an aggregate rather than a column, so no
     * `ORDER BY` could have carried it without a raw clause claiming NULLS LAST
     * about a `sum()`. The set is bounded; see
     * {@see IngestRejects::refusedOrigins()}.
     *
     * ⚠️ **THE TIE-BREAK IS RECENCY**, so two addresses with one refusal each —
     * which is what a quiet site looks like — do not swap places on every
     * render.
     *
     * @return array{0: list<RefusedOrigin>, 1: int}
     */
    private function refusals(): array
    {
        $origins = $this->rejects->refusedOrigins();

        usort($origins, static function (RefusedOrigin $a, RefusedOrigin $b): int {
            return $b->rejects <=> $a->rejects
                ?: $b->lastAt->getTimestamp() <=> $a->lastAt->getTimestamp();
        });

        return [
            array_slice($origins, 0, self::MAX_ADDRESSES_SHOWN),
            max(0, count($origins) - self::MAX_ADDRESSES_SHOWN),
        ];
    }
}
