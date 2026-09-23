<?php

declare(strict_types=1);

namespace App\Support;

use App\Console\Commands\PruneFailedJobs;
use App\Console\Commands\PruneFailedSignIns;
use App\Console\Commands\PruneFetchAttempts;
use App\Console\Commands\PruneOwnerChannel;
use App\Console\Commands\PrunePlatformMailSends;
use App\Console\Commands\PruneTrialOriginClaims;
use App\Console\Commands\ShowTableFootprint;
use App\Console\Commands\WatchPlatformHealth;
use App\Enums\RetentionScope;
use App\Models\PublicAudit;
use App\Services\Automation\AutomationRunRetention;
use App\Services\Export\ExportBuilder;
use App\Services\MagicLinkService;
use App\Services\Messaging\SendingHealth;
use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\TableFootprintLine;
use App\Services\Pixel\IngestRejects;
use App\Services\Visibility\GoogleRatingSnapshotRetention;
use App\Services\Visibility\ReviewLossDetection;
use App\Services\Warehouse\DerivedTables;
use App\Services\Warehouse\WarehouseRetention;

/**
 * Every table in this schema that something on a clock removes from, and what
 * it removes - decisions 8000-8019.
 *
 * ## ⛔ A POSITIVE LIST, AND THAT IS THE WHOLE ARGUMENT WITH `TenancyTest`'s
 * `$exempt`
 *
 * The obvious model for this is *"every table without row-level security is a
 * named exception"* (3146-3152): read `pg_class` for the whole schema, compare
 * against a hand-written exemption list, and a migration that ships an
 * unprotected table reddens the build instead of passing unseen. **That shape
 * is refused here and the reason is arithmetic.** RLS is the *normal* state and
 * a horizon is the *rare* one, by an order of magnitude - so RLS's list of
 * exceptions is the short side and every entry on it is a real argument
 * somebody made, while an exemption list in this shape would need well over a
 * hundred entries and nobody has ever made a hundred arguments. **The list
 * would be manufactured** - a wall of reasons written to get a build green,
 * reading afterwards as a wall of rulings, which is `CLAUDE.md` 314-316 at
 * schema scale.
 *
 * ⛔ **THIS PARAGRAPH CARRIED THREE HARD FIGURES AND ALL THREE HAD GONE STALE -
 * CORRECTED 2026-08-28 (wave 40 lane A, 10844).** It read *"112 of 161 tables
 * have it"* and *"sixteen tables of 161 after this slice, and fifteen before
 * it"*; on that day the schema held **168** tables with **117** row-level
 * secured and this list named **27**. **Not one of the three was ever going to
 * be re-read by the slice that made it wrong**, and this file's own next
 * paragraph already says where the live answer comes from - which is why the
 * figures are gone rather than updated. ⚠️ **The ARGUMENT never depended on
 * them**: it depends on the ratio, and the ratio has not moved. **Do not put a
 * number back here.** `php artisan db:footprint` counts both sides on the
 * schema in front of you.
 *
 * ⚠️ **SO THE INVERSION IS INVERTED.** This list names only what *has* a
 * horizon, and *"which tables have none"* is derived from `pg_class` by
 * {@see ShowTableFootprint} rather than written down by anybody. The population
 * is therefore complete by construction, and a table added to this schema next
 * month is on it the day it is created, with nobody to remember it.
 *
 * ⛔ **AND THAT IS WHY THIS LIVES IN `app/` RATHER THAN INSIDE THE LINT.**
 * `$exempt` sits in `TenancyTest` because the lint is its only reader. This has
 * two - the lint and the operator's report - and a copy in each is the prose
 * list 3149 deleted, with an extra copy.
 *
 * ## ⚠️ EVERY PERIOD IS THE CONSTANT AND NEVER A NUMBER TYPED HERE
 *
 * A literal `90` beside `ingest_rejects` would be right on the day it was typed
 * and silent on the day the constant moved, which is 2505's shape in the file
 * whose entire purpose is to stop a stale list misleading a reader. The three
 * periods this file states in words rather than in constants -
 * `magic_link_tokens`, `storage:prune` and the L3 note below - each say why.
 *
 * ## ⛔ AND A PERIOD THAT IS AN OPERATOR SETTING IS NOT A PERIOD UNTIL IT IS
 * SET — wave 41 lane D (11070, 11071)
 *
 * Five entries below state no period at all, because theirs is a registry row
 * with **no seed**: `automation_runs`, `fetch_attempts`,
 * `google_rating_snapshots`, `owner_replies` and `owner_notifications`. Each
 * one's manifest note says the same thing in the same words — ⛔ *"AN EMPTY ROW
 * DELETES NOTHING — a missing period is not zero days"* — and each one's pruner
 * prints a line and opens no query at all while the row is empty.
 *
 * ⛔ **UNTIL THIS SLICE THIS FILE SAID SO IN PROSE AND THE REPORT SAID THE
 * OPPOSITE IN A COLUMN.** `TableFootprintLine::rowsAreBounded()` read the
 * *scope* and nothing else, so all five answered `true` — **bounded** — on
 * every deployment that has ever existed, including the one holding an account
 * holder's own free text (`owner_replies.body`). A committed test asserted it
 * of `automation_runs` by name. **The `key` field is what lets the report ask**,
 * and {@see TableFootprint} is where it is asked.
 *
 * ⚠️ **AN UNSET PERIOD IS A LEGITIMATE STATE AND THE REPORT MUST NOT READ AS
 * SCOLDING ONE.** These five are unset on `storage.retention_days.*`'s stated
 * ground — the drafted privacy policy commits to nothing about these kinds and
 * `docs/LEGAL-DRAFTS-V1.md` leaves the periods to counsel — so what is owed is
 * a report that distinguishes *bounded*, *scheduled with no period yet* and
 * *nothing sweeps this at all*, not one that calls the middle state a defect.
 *
 * ## ⛔ WHAT THIS IS NOT
 *
 * It is **not** a claim that a table without a horizon needs one, and it is not
 * a to-do list. *No horizon* is the correct answer for most of this schema: a
 * row per business, per location, per user or per plan is bounded by the
 * population it describes, and deleting one on a clock would delete the thing
 * itself. The tables worth arguing about are the ones written once per *event*,
 * and the report is what distinguishes them - by measuring inserts against
 * updates on a real install, not by anybody classifying them here.
 *
 * ## ⛔ `jobs` HAS NO ENTRY HERE AND MUST NEVER GET ONE - REFUSED 2026-08-23
 * (8750-8779)
 *
 * 8620 left it open: `jobs` holds the same cleartext payloads as `failed_jobs`,
 * under the same exemption from row-level security, and a job dispatched to a
 * queue name no worker pops sits there permanently. **The instrument is still
 * not a horizon.** A `failed_jobs` row is a finished thing nobody will act on;
 * a `jobs` row is *pending work* - an undelivered obligation - and a clock that
 * deleted one would silently drop a review invite, a text-back or a sign-in
 * mail, with the queue reporting healthy throughout.
 *
 * ⛔ **AND IT IS WORSE THAN MERELY WRONG: IT WOULD DELETE THE SYMPTOM.** The
 * only reason a `jobs` row is old is that nothing is popping its queue, so a
 * sweep would keep the table small, keep this list looking complete and keep
 * `db:footprint` calm **precisely while the defect it was reaching for was
 * running.** That is 314-316 with a `DELETE` attached.
 *
 * ⚠️ **AND {@see RetentionScope} MAY NOT BE WIDENED TO SAY IT EITHER.** A third
 * case meaning *"a consumer removes these"* would be this file claiming a
 * remover it cannot promise exists - a queue with no worker is exactly the
 * state it would be reporting as bounded. **What the report says instead is
 * measured rather than declared**: the removal rate on
 * {@see TableFootprintLine}, which reads a delete counter this class has no
 * business predicting.
 */
final class TableHorizons
{
    /**
     * Table name to the horizon that acts on it.
     *
     * ⛔ **`key` IS THE REGISTRY ROW THE PERIOD LIVES IN, AND `null` MEANS THE
     * PERIOD IS A CONSTANT IN CODE** — wave 41 lane D (11070). The class
     * docblock above carries why it exists; what matters at the call site is
     * that it is **the owning class's own constant and never a string typed
     * here**, which is this file's constants rule applied to the key rather
     * than to the number. `RetentionTest`'s *"a horizon whose period is an
     * operator setting names the row its own pruner reads"* resolves the
     * constant out of the pruner's source and compares it, so the two cannot
     * drift into naming different rows.
     *
     * @return array<string, array{command: string, keeps: string, scope: RetentionScope, key: string|null}>
     */
    public static function all(): array
    {
        return [
            ...self::warehouse(),
            ...self::storageObjects(),

            'public_audits' => [
                'command' => 'audits:prune',
                'keeps' => PublicAudit::RETENTION_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            // ⚠️ **A DAY PAST EXPIRY, WHICH IS NOT A CONSTANT ANYWHERE.**
            // `MagicLinkService::prune()` computes `now()->subDay()` against
            // `expires_at`, and the expiry itself is
            // `MagicLinkService::LIFETIME_MINUTES` from issue. Quoting the
            // fifteen minutes here would describe the wrong number: what bounds
            // the table is the day, and the day is a literal in that method.
            'magic_link_tokens' => [
                'command' => 'auth:prune-magic-links',
                'keeps' => 'a day past expiry ('.app(MagicLinkService::class)->lifetimeMinutes().' minutes)',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            // ⛔ **THE ONE ENTRY HERE WHOSE TABLE IS NOT OURS, AND THE ONLY
            // ONE HOLDING OTHER PEOPLE'S PERSONAL DATA IN CLEARTEXT WITH NO
            // ROW-LEVEL SECURITY AND NO ERASURE PATH.** A `failed_jobs` row is
            // the serialised job, so it is whatever the constructor was handed —
            // a recipient's email address, a caller's mobile number, a sign-in
            // URL with its token in it. Nine files in `app/` deform their design
            // around that fact in comments and none of them closed it.
            // {@see PruneFailedJobs} carries the period's argument, which
            // precedent governs it, and — the part that must not be read past —
            // that a horizon is not an erasure path (8610-8639).
            'failed_jobs' => [
                'command' => 'jobs:prune-failed',
                'keeps' => PruneFailedJobs::RETENTION_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            // ⛔ **THE LARGEST TABLE IN THIS SCHEMA, AND IT WAS ON NOBODY'S
            // LIST.** It is here because the derived report put it at the top on
            // its first run, not because anybody remembered it — which is the
            // whole argument for deriving the population rather than writing it
            // down. {@see PrunePlatformMailSends} carries why it is the one of
            // the hundred and forty-six that needed no ruling.
            'platform_mail_sends' => [
                'command' => 'mail:prune-send-meter',
                'keeps' => PrunePlatformMailSends::RETENTION_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            // ⛔ **A REGISTER OF ADDRESSES THAT WERE *TRIED*, WHICH IS MOSTLY
            // NOT A REGISTER OF OUR USERS** (9860-9879). A credential-stuffing
            // list is somebody else's breach corpus, so the commonest row here
            // names a person with no account and no relationship with this
            // platform — and there is no account for an erasure to reach.
            // {@see PruneFailedSignIns} carries why the period is a CEILING
            // derived from a join rather than a number somebody picked, and why
            // shortening it needs no engineering and lengthening it reopens
            // 7886.
            'failed_sign_ins' => [
                'command' => 'auth:prune-failed-sign-ins',
                'keeps' => PruneFailedSignIns::RETENTION_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            'trial_claims' => [
                'command' => 'trials:prune-origins',
                'keeps' => PruneTrialOriginClaims::RETENTION_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            'tenant_exports' => [
                'command' => 'exports:prune',
                'keeps' => ExportBuilder::LINK_EXPIRY_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            'ingest_rejects' => [
                'command' => 'pixel:prune-rejects',
                'keeps' => app(IngestRejects::class)->retentionDays().' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            'sending_health_windows' => [
                'command' => 'messaging:prune-sending-health',
                'keeps' => app(SendingHealth::class)->retentionDays().' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            // ⛔ **TWO TABLES SWEPT BY A COMMAND THAT IS NOT A PRUNER**, and it
            // is deliberate on both: `WatchPlatformHealth` writes neither, but
            // it is the sweep that reads them, so a range delete that matches
            // nothing on an ordinary day rides its lock rather than taking one
            // of its own. Their two periods differ by a factor of twelve and
            // {@see WatchPlatformHealth::KEEP_DAYS} carries the argument for
            // why they must never be harmonised.
            'platform_health_windows' => [
                'command' => 'ops:watch-platform-health',
                'keeps' => WatchPlatformHealth::KEEP_DAYS.' days',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],
            'operator_alerts' => [
                'command' => 'ops:watch-platform-health',
                'keeps' => app(OperatorAlerts::class)->retentionDays().' days, or the quiet window if that is longer',
                'scope' => RetentionScope::Rows,
                'key' => null,
            ],

            // ⛔ **THE PERIOD IS NOT A CONSTANT, ON `storageObjects()`'s EXACT
            // REASON — decisions 10184, 10185, 10260.**
            // `automation.retention_days` is a registry row deliberately with
            // no seed, and an unset one prunes nothing at all.
            // `automation:prune-runs` prints every unset key on every run and
            // is the authority.
            'automation_runs' => [
                'command' => 'automation:prune-runs',
                'keeps' => 'per operator setting',
                'scope' => RetentionScope::Rows,
                'key' => AutomationRunRetention::RETENTION_KEY,
            ],

            // ⛔ **THE PERIOD IS NOT A CONSTANT HERE EITHER, EVEN THOUGH NO
            // READER LOOKS BACK PAST SEVEN DAYS** — decisions 10189(f),
            // 10260-10269. `fetch.attempts_retention_days` is a registry row
            // deliberately with no seed: the table's own creating migration
            // names a real audit capability past what any reader needs, and
            // this file may not decide how long that answer stays available
            // on the owner's behalf. `fetch:prune-attempts` prints the unset
            // key on every run and is the authority.
            'fetch_attempts' => [
                'command' => 'fetch:prune-attempts',
                'keeps' => 'per operator setting',
                'scope' => RetentionScope::Rows,
                'key' => PruneFetchAttempts::RETENTION_KEY,
            ],

            // ⛔ **THE PERIOD IS ALSO NOT A CONSTANT, AND ALSO CLAMPED —
            // review-loss detection, wave 38 lane D.**
            // `review_loss.snapshot_retention_days` is a registry row
            // deliberately with no seed, on `automation.retention_days`'s
            // exact argument: an unset period prunes nothing. ⚠️ **UNLIKE ITS
            // SIBLINGS ABOVE, A STATED PERIOD IS ALSO FLOOR-CLAMPED** —
            // {@see GoogleRatingSnapshotRetention::retentionDays()} — because
            // {@see ReviewLossDetection::flatRunStart()} reads back up to
            // `review_loss.pause_flat_days` of this table's own history on
            // every evaluation, and a shorter stated period would silently
            // make pause detection permanently unreachable rather than merely
            // trimming disk. `review-loss:prune-snapshots` prints the unset
            // key, and the floor it clamps to, on every run.
            'google_rating_snapshots' => [
                'command' => 'review-loss:prune-snapshots',
                'keeps' => 'per operator setting, floor-clamped to review_loss.pause_flat_days',
                'scope' => RetentionScope::Rows,
                'key' => GoogleRatingSnapshotRetention::RETENTION_KEY,
            ],

            // ⛔ **TWO TABLES ON ONE PERIOD, AND THE FIRST ENTRY HERE WHOSE
            // SUBJECT IS A PERSON'S OWN FREE TEXT — wave 40 lane A (10834).**
            // `owner_replies.body` is what a business's account holder typed
            // back to us, and until this slice **fourteen `Prune*` commands
            // existed and not one touched it**, while that table's own creating
            // migration cited *"a retention policy to apply to it"* as part of
            // its argument for existing. `owner_channel.retention_days` is a
            // registry row deliberately with no seed, on
            // `storage.retention_days.*`'s stated ground rather than by habit:
            // the drafted privacy policy commits to nothing about this kind and
            // `docs/LEGAL-DRAFTS-V1.md` leaves the period to counsel.
            // `owner-channel:prune` prints the unset key on every run and is the
            // authority. ⚠️ **One key covers both halves because they are one
            // conversation** — sweeping the outbound record and keeping the
            // reply leaves a row that reads as an answer to nothing.
            'owner_replies' => [
                'command' => 'owner-channel:prune',
                'keeps' => 'per operator setting',
                'scope' => RetentionScope::Rows,
                'key' => PruneOwnerChannel::RETENTION_KEY,
            ],

            'owner_notifications' => [
                'command' => 'owner-channel:prune',
                'keeps' => 'per operator setting',
                'scope' => RetentionScope::Rows,
                'key' => PruneOwnerChannel::RETENTION_KEY,
            ],
        ];
    }

    /**
     * Every command named above, once each.
     *
     * @return list<string>
     */
    public static function commands(): array
    {
        return array_values(array_unique(
            array_map(static fn (array $h): string => $h['command'], self::all())
        ));
    }

    /**
     * Every registry key a horizon's period lives in, once each.
     *
     * ⚠️ **THE POPULATION {@see TableFootprint} HAS TO READ, AND NOTHING
     * ELSE.** It is deliberately not *"every retention key in the manifest"*:
     * `storage.retention_days.{kind}` is keyed by object kind rather than by
     * table, so no single row answers *"is `voicemails` bounded?"* and the four
     * storage entries carry `key => null` for that reason rather than by
     * oversight — their scope is already `Bytes`, so the report calls their row
     * counts unbounded whatever anybody sets.
     *
     * @return list<string>
     */
    public static function periodKeys(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (array $h): ?string => $h['key'], self::all()),
            static fn (?string $key): bool => $key !== null,
        )));
    }

    /**
     * The derived layers, whose horizons are one constant per layer.
     *
     * ⚠️ **DERIVED FROM `DerivedTables::DERIVED_TABLES` RATHER THAN LISTED**, so
     * a seventh mart cannot ship here as un-horizoned while `warehouse:prune`
     * is already sweeping it. That constant is the same one the sweep loops
     * over, which makes this entry structurally unable to disagree with it.
     *
     * ⛔ **`l3_benchmark_cohort_daily` IS ABSENT AND THAT IS CORRECT.**
     * `DerivedTables::NETWORK_TABLES` is a separate constant because the *purge*
     * is a different statement - L3 carries no `business_id` by schema - and
     * `WarehouseRetention::prune()` loops over `DERIVED_TABLES` only. **So L3
     * has no horizon**, and the report says so rather than this file quietly
     * folding it in with its neighbours.
     *
     * @return array<string, array{command: string, keeps: string, scope: RetentionScope, key: string|null}>
     */
    private static function warehouse(): array
    {
        $horizons = [];

        $days = [
            'l1' => WarehouseRetention::L1_RETENTION_DAYS,
            'l2' => WarehouseRetention::l2RetentionDays(),
        ];

        foreach (DerivedTables::DERIVED_TABLES as $layer => $tables) {
            foreach ($tables as $table) {
                $horizons[$table] = [
                    'command' => 'warehouse:prune',
                    'keeps' => $days[$layer].' days',
                    'scope' => RetentionScope::Rows,
                    'key' => null,
                ];
            }
        }

        return $horizons;
    }

    /**
     * The four tables `storage:prune` reaches, none of which loses a row.
     *
     * ⛔ **THE PERIOD IS NOT A CONSTANT AND MUST NOT BE STATED AS ONE**, which
     * is the one place this file's own rule about constants does not apply.
     * `storage.retention_days.{kind}` is a registry row per kind, deliberately
     * with no seed (4941-4942), and an unset one prunes nothing at all. A
     * figure printed here would be a fact about a fresh checkout, which is
     * `CLAUDE.md`'s *a seed is not a deployment* exactly. `storage:prune`
     * prints every unset key on every run and is the authority.
     *
     * @return array<string, array{command: string, keeps: string, scope: RetentionScope, key: string|null}>
     */
    private static function storageObjects(): array
    {
        $tables = [
            'inbound_media',
            'voicemails',
            'knowledge_sources',
            'campaign_recipients',
        ];

        $horizons = [];

        foreach ($tables as $table) {
            $horizons[$table] = [
                'command' => 'storage:prune',
                'keeps' => 'per kind, set in Ops - run storage:prune to see which are unset',
                'scope' => RetentionScope::Bytes,
                'key' => null,
            ];
        }

        return $horizons;
    }
}
