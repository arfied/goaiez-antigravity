<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLogEntry;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;

/**
 * The immutable compliance record.
 *
 * `29` §2 rule 42: every sensitive action reaches an append-only audit log.
 * Distinct from the activity feed in audience and in detail — the feed is what
 * the owner sees in plain language, this is what an auditor reads in full, and
 * conflating them means either leaking internals into the owner's view or
 * losing detail the record needs.
 *
 * **FOUND-06 names spatie/laravel-activitylog and this deliberately does not
 * use it.** That package brings its own table shape, and `audit_log` already
 * exists with the columns DATA-MODEL §5.12 specifies — actor, action, a
 * polymorphic entity, metadata. Adopting it would mean either two audit tables
 * or reshaping ours to match a dependency, and adding a dependency needs
 * approval regardless (CLAUDE.md). See docs/DECISIONS.md 183.
 *
 * The actor is a string, not a user foreign key, because automation is a
 * first-class actor here: 'autopilot', 'system', or 'user:14'. A nullable user
 * id would model "nobody did this", which is never true.
 */
final class AuditService
{
    /**
     * Record a sensitive action against the current tenant.
     *
     * @param  array<string, mixed>  $metadata  For a change, include what it was
     *                                          before and after — an audit entry
     *                                          that records only the new value
     *                                          cannot answer what happened.
     */
    public function record(
        string $action,
        string $actor,
        ?Model $entity = null,
        array $metadata = [],
    ): AuditLogEntry {
        Tenancy::idOrFail();

        return AuditLogEntry::create([
            'actor' => $actor,
            'action' => $action,
            'entity_type' => $entity === null ? null : $entity::class,
            'entity_id' => $entity?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Has this tenant ever done this, according to the one record that cannot
     * be edited or deleted?
     *
     * ⚠️ **THIS EXISTS SO THAT ASKING THE QUESTION DOES NOT BREAK THE
     * CHOKEPOINT** (2047). `Architecture\ConsentTest` holds `AuditLogEntry` to
     * exactly two services — this one writes it, `ConsentService` reads it —
     * and it caught {@see Export\ExportBuilder::purgeAllFor()} reaching for the model
     * directly on the day that guard was rewritten. The lint is right and the
     * question is legitimate, so the question moves here rather than the
     * allowlist growing a third entry.
     *
     * ⚠️ **A DURABILITY READ, NOT A CONVENIENCE ONE.** The reason a caller wants
     * this is that `audit_log` outlives the rows it describes: the model refuses
     * updates and deletes (DATA-MODEL §5.14), and nothing in this application
     * prunes it. That is the property {@see ExportBuilder} depends on, and it is
     * why "did this ever happen" cannot be answered from the feature's own
     * table.
     *
     * Scoped to the tenant in context, like every other read here.
     */
    public function everRecorded(string $action): bool
    {
        Tenancy::idOrFail();

        return AuditLogEntry::query()->where('action', $action)->exists();
    }

    /**
     * Record a change, capturing both sides of it.
     *
     * Threshold changes are audited with old and new values (`29` §19.3), and
     * that is the general shape rather than a special case.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function recordChange(
        string $action,
        string $actor,
        array $before,
        array $after,
        ?Model $entity = null,
    ): AuditLogEntry {
        return $this->record($action, $actor, $entity, [
            'before' => $before,
            'after' => $after,
        ]);
    }
}
