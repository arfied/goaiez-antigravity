<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Models\SiteChangeQuarantine;
use App\Services\AuditService;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * A fix that measured badly, resting on the site it measured badly on —
 * `BUILD-PLAN` §2.11.3 slice H: *"a sick change rests; the automation is not
 * retried onto the same site"*.
 *
 * ⛔ **THE ONLY WRITER OF `site_change_quarantines`**, held by an
 * `Architecture\ActuationTest` lint. What a second writer would skip is the
 * release triple — `released_at`, `released_by`, `released_reason` move together
 * or not at all — and the partial unique index that makes *live* a state rather
 * than a pile of rows. A caller writing one of those on its own puts an
 * automation back onto a customer's website with nobody's name against it.
 *
 * ## Both ends of the door ship together, and 3769/3789 is why
 *
 * ⛔ **AN AUTOMATIC CONTAINMENT WITH NO HUMAN EXIT IS UNSHIPPABLE**, and one
 * that lifts on a timer is not a containment. 3780–3796 is the whole argument,
 * learned on the number pool: the automatic quarantine shipped first, the
 * release shipped later, and in between the only way out was hand-written SQL.
 * So {@see self::release()} and `actuation:release-quarantine` land in this same
 * slice as {@see self::quarantine()}, with a typed reason and a named actor on
 * both — `NumberStateCommand`'s four disciplines, of which the two that apply
 * here are exactly those.
 *
 * ⛔ **NOTHING RE-ARMS AND NOTHING RE-QUARANTINES A RELEASED FIX BY ITSELF.**
 * A released fix may be applied again, measured again, and quarantined again on
 * its own next regression — that is the automatic path, and it is the one 3796
 * records as missing on the number pool. What does **not** exist is a second
 * opinion about a release: an operator who releases a genuinely sick fix has put
 * it back, and the next measurement is what stops it, thirty days later.
 *
 * ## What a quarantine does and does not stop
 *
 * ⚠️ **IT STOPS THIS PLATFORM WRITING THAT KIND OF CHANGE TO THAT SITE. IT DOES
 * NOT STOP THE TENANT.** `29` §2 rule 44's advisory rung still hands the owner
 * the same gated copy to paste themselves, which is 5745's ruling one slice on:
 * the site-writing path is gated absolutely and the paste-it-yourself path is
 * not, because rule 36 and rule 32 are about what *we* publish.
 */
final class SiteChangeQuarantines
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Is this kind of change resting on this site?
     *
     * ⚠️ **THE READER IS THE POINT OF THE TABLE** (272, 1222). A quarantine
     * nothing consults is a row, not a containment — `sending_health_windows`
     * with a website attached — so the gate in `PublishGrowthPageJob` is part of
     * this slice rather than a later one.
     */
    public function isQuarantined(int $locationId, string $changeType): bool
    {
        return SiteChangeQuarantine::query()
            ->where('location_id', $locationId)
            ->where('change_type', $changeType)
            ->whereNull('released_at')
            ->exists();
    }

    /**
     * Why this fix is resting, in the words it was quarantined with.
     *
     * ⚠️ **THE RETRY READS IT RATHER THAN REBUILDING IT.** A revert that failed
     * is attempted again a day later, and re-deriving the sentence would mean
     * re-measuring — which reads the marts a second time and could, if a mart
     * were rebuilt in between, produce a different percentage against the same
     * verdict. The owner-facing sentence is a fact about the measurement that
     * was made, not about the one that could be made today.
     */
    public function reasonFor(int $locationId, string $changeType): ?string
    {
        $quarantine = SiteChangeQuarantine::query()
            ->where('location_id', $locationId)
            ->where('change_type', $changeType)
            ->whereNull('released_at')
            ->first();

        return $quarantine instanceof SiteChangeQuarantine ? $quarantine->quarantined_reason : null;
    }

    /**
     * Rest this fix on this site.
     *
     * ⚠️ **IDEMPOTENT, BECAUSE THE MEASURER RETRIES.** A second regression on a
     * fix that is already resting is not a second quarantine — the partial
     * unique index would refuse it, and a caller catching that violation would
     * be reading a constraint as control flow. It answers false and writes
     * nothing.
     */
    public function quarantine(
        int $locationId,
        string $changeType,
        ?int $siteChangeId,
        ActuationActor $actor,
        string $reason,
    ): bool {
        if ($this->isQuarantined($locationId, $changeType)) {
            return false;
        }

        $quarantine = SiteChangeQuarantine::create([
            'location_id' => $locationId,
            'change_type' => $changeType,
            'site_change_id' => $siteChangeId,
            'quarantined_at' => now(),
            'quarantined_by' => $actor->kind,
            'quarantined_reason' => $reason,
        ]);

        $this->audit->record(
            'site_change.quarantined',
            $actor->auditActor(),
            $quarantine,
            [
                'location_id' => $locationId,
                'change_type' => $changeType,
                'site_change_id' => $siteChangeId,
                'reason' => $reason,
            ],
        );

        return true;
    }

    /**
     * Let this fix run on this site again.
     *
     * ⛔ **THE ACTOR AND THE REASON ARE REQUIRED AND HAVE NO DEFAULT** (3789).
     * `ops:cli` names no human, and putting an automation back onto a
     * customer's website after it measurably harmed that website is the most
     * answerable-for act in this domain. `29` §2 rule 42 wants the person, not
     * the terminal.
     */
    public function release(int $locationId, string $changeType, string $actor, string $reason): bool
    {
        if (trim($actor) === '' || trim($reason) === '') {
            return false;
        }

        $quarantine = SiteChangeQuarantine::query()
            ->where('location_id', $locationId)
            ->where('change_type', $changeType)
            ->whereNull('released_at')
            ->first();

        if (! $quarantine instanceof SiteChangeQuarantine) {
            return false;
        }

        $quarantine->forceFill([
            'released_at' => now(),
            'released_by' => trim($actor),
            'released_reason' => trim($reason),
        ])->save();

        $this->audit->record(
            'site_change.quarantine_released',
            trim($actor),
            $quarantine,
            [
                'location_id' => $locationId,
                'change_type' => $changeType,
                'reason' => trim($reason),
                // The rest is what an operator is answering for: how long it had
                // been resting, and what put it there.
                'quarantined_at' => $quarantine->quarantined_at->toIso8601String(),
                'quarantined_reason' => $quarantine->quarantined_reason,
            ],
        );

        return true;
    }

    /**
     * Every fix currently resting for this tenant, newest first.
     *
     * ⚠️ **TENANT-SCOPED BY CONTEXT**, like every other read here: the global
     * scope raises with no tenant established rather than returning nothing, and
     * row-level security stands underneath it.
     *
     * @return list<QuarantinedFix>
     */
    public function live(?int $locationId = null): array
    {
        Tenancy::idOrFail();

        /** @var Collection<int, SiteChangeQuarantine> $rows */
        $rows = SiteChangeQuarantine::query()
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->whereNull('released_at')
            // ⚠️ **BY ID, NOT BY `quarantined_at`** — `ConventionsTest`'s
            // NULLS-FIRST lint, and the reason it is right here rather than
            // merely permitted: Postgres sorts NULLs **first** on a `DESC`, so
            // every timestamp ordering in this codebase either says `NULLS LAST`
            // out loud or uses the key. There is at most one live row per
            // `(location, change_type)` and the id is monotonic with the
            // timestamp for every writer there is, so this is the same order
            // with nothing to get wrong.
            ->orderByDesc('id')
            ->get();

        return array_values($rows->map(static fn (SiteChangeQuarantine $row): QuarantinedFix => new QuarantinedFix(
            (int) $row->id,
            $row->location_id,
            $row->change_type,
            $row->site_change_id,
            CarbonImmutable::instance($row->quarantined_at),
            $row->quarantined_reason,
        ))->all());
    }
}
