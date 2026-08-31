<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\ImpersonationSession;
use App\Models\RegistryChange;
use App\Services\Config\DefaultsRegistry;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The reader of the compliance record — `28` §9.2's audit explorer, §14.1.
 *
 * ⚠️ **`audit_log` had eight writers in `app/` and no reader at all.** Every
 * service that files a sensitive action wrote into a table nothing in this
 * application could open. `29` §2 rule 42 requires the record; a record nobody
 * can read satisfies the letter of it and none of the point.
 *
 * It is decision 272's shape **inverted**, and the inversion is why it survived
 * this long. 272's instances are tables missing a *writer*, which eventually
 * shows up as an empty screen somebody complains about. A missing *reader*
 * shows up as nothing at all: every test that files an entry passes, the rows
 * accumulate, and the absence is visible only to somebody trying to answer a
 * question with them — which on this table is an auditor, on the worst day to
 * find out.
 *
 * ⚠️ **NOT `App\Services\Audit\*`, and the neighbouring namespace is a trap.**
 * `App\Services\Audit` is row 2's *free instant audit* — a marketing funnel that
 * scores a stranger's website. It has nothing to do with `audit_log`. This class
 * sits beside `AuditService` instead, because writer and reader of one table
 * belong next to each other, and because a reader who lands in the wrong
 * `Audit` directory should find nothing that looks plausible.
 *
 * ## The two questions, and why they take different paths
 *
 * `28` §14.1 states the contract: the explorer answers *"everything agent X
 * did"* and *"everyone who touched account Y"* in one query each. Those read
 * like one screen with two filters. They are not, because of RLS.
 *
 * - **"Everyone who touched account Y"** names its tenant, so it is 569's
 *   pattern exactly: `Tenancy::actingAs($id)` sets `app.business_id`,
 *   `tenant_isolation` admits that one business's rows, and `audit_log` is read
 *   under the same boundary every other reader in this codebase works under.
 *
 * - **"Everything agent X did"** names no tenant, and is therefore the account
 *   list of decision 569 wearing a different hat. `audit_log` is RLS-`FORCE`d on
 *   `app.business_id`; a `WHERE actor = 'support:14'` across tenants matches
 *   nothing, and `withoutGlobalScopes()` does not help, because it drops the
 *   application scope and RLS sits beneath it. Serving it from `audit_log`
 *   would mean a policy admitting platform staff to every tenant's compliance
 *   record — a wider hole than the one 569 refused, opened for a read-only
 *   screen.
 *
 *   **It is served from the two platform-scoped stores instead**, and neither
 *   needs a new policy: `impersonation_sessions` (`USING (true)`, decision 562)
 *   is the complete index of staff reaching *into* a tenant, and
 *   `registry_changes` (509) is the complete record of staff changing something
 *   *platform-global*. Between them they cover both ways a staff member's action
 *   leaves their own account.
 *
 * ⚠️ **What that view does not cover, stated because it will otherwise be
 * assumed.** A staff member who also owns a business, acting on their own tenant
 * through `/admin/locations/{id}/settings`, writes `audit_log` under their own
 * `business_id` with actor `user:{id}` — and does not appear here. That is not a
 * gap in the index: it is somebody acting inside their own account, which is not
 * cross-tenant access and shows up in that account's own trail. `staffActivity()`
 * answers "where has this person been that was not their own account", and the
 * distinction is pinned by a test so it cannot quietly become "everything".
 *
 * ## Reading is itself recorded
 *
 * `28` §14.1's first sentence attributes internal **reads** of sensitive
 * objects, not only writes. So opening an account's trail files an
 * `audit.explored` row into that account's trail. The self-reference is
 * deliberate and the row is never filtered out of the view: an auditor asking
 * who has been through this customer's record has to see us.
 *
 * It is also what keeps this screen from being a second, unrecorded door into
 * tenant data next to the reasoned, time-limited one slice 1 built. It is a
 * narrower door — metadata about actions rather than the customer content an
 * impersonated session renders — which is why it needs no session, and it is
 * still a door.
 *
 * ## ⚠️ The narrowness of that door is a convention, not a mechanism
 *
 * The screen renders `metadata`, because a trail showing only the verb cannot
 * answer what changed and that is what `recordChange()` exists to store. What
 * makes rendering it safe is `AuditLogEntry`'s own rule — *"metadata never
 * carries customer personal data or secrets; name the entity by type and id"* —
 * which every writer today honours, `AnalyzeReviewJob` most explicitly, since
 * it holds review text back and says so on the method.
 *
 * Nothing enforces it. A writer that put customer text in `metadata` would
 * surface it here, to a reader with no impersonation session and no PHI check.
 *
 * ⚠️ **`DataClassification` now has a writer** (`TenantClassification`, merged
 * while this branch was open), so "when PHI lands" is no longer the condition —
 * PHI tenants exist. **This class still does not consult it, and today that is
 * correct rather than deferred**: `28` §9.4 masks *message bodies and PHI
 * objects*, and audit metadata is neither. `business.reclassified` carries a
 * classification and an operator's typed reason; nothing here carries a
 * patient, a phone number or a review's words.
 *
 * The condition to watch is therefore not "does PHI exist" but **"has a writer
 * started putting content in `metadata`"** — and the day one does, this is the
 * second surface that needs `28` §9.4's mask, beside the impersonation seam
 * decision 571 left open.
 */
final class AuditExplorer
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
        private readonly DefaultsRegistry $registry,
    ) {}

    /**
     * Name an account, and record having done so.
     *
     * Returns the business name, or null when the reference names nothing. A
     * wrong number and a number belonging to nobody are deliberately
     * indistinguishable, on decision 569's reasoning — this screen discloses
     * whether *this* account exists to somebody who already had its number, and
     * nothing more.
     *
     * ⚠️ The read is recorded **here**, on the deliberate act of opening an
     * account, rather than in {@see entriesFor()}. That method runs on every
     * Livewire render — every keystroke in the search box — and one audit row
     * per keystroke is a log nobody can read, which is the failure mode
     * `AdminForm` already guards against by only writing when something moved.
     */
    public function openAccount(int $businessId, string $actor): ?string
    {
        $name = Tenancy::actingAs(
            $businessId,
            fn (): mixed => Business::query()->whereKey($businessId)->value('name'),
        );

        if (! is_string($name)) {
            return null;
        }

        Tenancy::actingAs($businessId, function () use ($actor): void {
            $this->audit->record(
                action: 'audit.explored',
                actor: $actor,
                metadata: ['surface' => 'admin.audit-account'],
            );
        });

        return $name;
    }

    /**
     * Run a read against one account's trail, inside that account's tenancy.
     *
     * The callback shape rather than a returned builder is the point: a builder
     * handed back to a caller is executed wherever the caller happens to be,
     * which for a Livewire component is inside `render()` with no tenant set —
     * `Tenancy::idOrFail()` would throw, and the fix somebody reaches for is
     * `withoutGlobalScopes()`, which turns a loud failure into a silent
     * cross-tenant read attempt. Everything the caller needs has to be
     * materialised before the closure returns.
     *
     * @template TReturn
     *
     * @param  callable(Builder<AuditLogEntry>): TReturn  $read
     * @return TReturn
     */
    public function entriesFor(int $businessId, callable $read): mixed
    {
        return Tenancy::actingAs(
            $businessId,
            fn (): mixed => $read(AuditLogEntry::query()),
        );
    }

    /**
     * Every account this staff member has been inside.
     *
     * One query, over the platform-scoped index rather than over N tenants'
     * logs. The per-session counters (`page_views`, `writes`) are on the row
     * already — decision 562 put them there rather than a row per view — so the
     * shape of a session is answerable without opening the account at all, and
     * opening it is a separate recorded act.
     *
     * @return LengthAwarePaginator<int, ImpersonationSession>
     */
    public function sessionsBy(?int $agentId, int $perPage): LengthAwarePaginator
    {
        // ⚠️ Delegated, not queried. `ArchitectureTest` holds
        // `ImpersonationSession` to one service — reads included — and the
        // reason is on that test: the table's RLS policy admits everything by
        // construction (562), so the chokepoint is the whole protection. A
        // second reader here would have been that lint weakened by a screen.
        return $this->impersonation->history($agentId, $perPage, pageName: 'sessions');
    }

    /**
     * Every platform-global setting this staff member has moved.
     *
     * The other half of "everything agent X did", and the half with no tenant to
     * scope it to: `registry_changes` exists (509) precisely because
     * `AuditService` cannot record a change that belongs to no business. Leaving
     * it out would answer the question with the tenant half only — and the
     * platform half is where plan prices live.
     *
     * @return LengthAwarePaginator<int, RegistryChange>
     */
    public function registryChangesBy(?string $actor, int $perPage): LengthAwarePaginator
    {
        // ⚠️ Delegated, for the same reason `sessionsBy()` is. Both stores this
        // class reads are held to one owning service by a lint that names
        // *reading*, and both lints were written before this screen existed —
        // so adding a query here twice would have been two chokepoints weakened
        // by one feature, each looking reasonable on its own diff.
        return $this->registry->changesBy($actor, $perPage, pageName: 'settings');
    }
}
