<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\ImpersonationSession;
use App\Models\RegistryChange;
use App\Models\StaffEventRecord;
use App\Services\AuditExplorer;
use App\Services\Staff\StaffDirectory;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\StaffActor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Everything agent X did" — `28` §14.1, the other half of the audit explorer.
 *
 * ⚠️ **THIS IS NOT `audit_log` FILTERED BY ACTOR, AND IT CANNOT BE.** That is
 * the whole design of this screen and the thing a later change will want to
 * "simplify" back.
 *
 * `audit_log` is RLS-`FORCE`d on `app.business_id`. A platform admin has no
 * tenant, so `where('actor', 'support:14')` across tenants matches nothing, and
 * `withoutGlobalScopes()` does not help — it drops the application scope and RLS
 * is the layer beneath it. Making it work means a policy admitting platform
 * staff to **every tenant's compliance record**, which is a wider hole than the
 * one decision 569 declined to open for the account list, opened here for a
 * read-only screen. It is not opened.
 *
 * The question is answered from the two stores that already have no tenant, so
 * no policy changes and nothing new becomes visible:
 *
 * - **`impersonation_sessions`** (562) — every time a staff member reached into
 *   a customer's account, with the reason they typed, the ticket they named, the
 *   clock they were on, and how many pages they read and rows they wrote. Those
 *   counters are on the row already, which is why the shape of a visit is
 *   answerable without opening the account.
 * - **`registry_changes`** (509) — every platform-global setting they moved,
 *   with before and after. This half exists because `AuditService` structurally
 *   cannot record a change belonging to no business, and leaving it out would
 *   answer the question with the tenant half only — while the platform half is
 *   where plan prices live.
 *
 * ## ⚠️ What it deliberately does not claim
 *
 * A staff member who also owns a business, working on their own tenant through
 * `/admin/locations/{id}/settings`, writes `audit_log` under their own
 * `business_id` and does not appear here. That is not a hole in the index: it is
 * somebody acting inside their own account, which is not cross-tenant access,
 * and it shows up in that account's own trail where it belongs. The heading says
 * *"in customers' accounts"* rather than *"everything"* for that reason, and a
 * test pins the distinction so the claim cannot quietly widen.
 */
final class StaffActivity extends Component
{
    use WithPagination;

    /** The staff member being asked about, or null for everyone. */
    public string $agent = '';

    /**
     * ⚠️ A constant, not a public property, and the difference is reachable.
     *
     * Every public property on a Livewire component is settable from the
     * browser, so a public `$perPage` is a page size the client chooses —
     * `perPage = 1000000` asks Postgres for a million rows and PHP to hydrate
     * them. `AdminTable` declares one publicly and five screens already inherit
     * it; that is pre-existing and belongs to the shell rather than to this
     * slice, but there was no reason to add a sixth.
     */
    private const PER_PAGE = 25;

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    public function updatedAgent(): void
    {
        $this->resetPage('sessions');
        $this->resetPage('settings');
        $this->resetPage('staffEvents');
    }

    public function clearAgent(): void
    {
        $this->agent = '';
        $this->updatedAgent();
    }

    /**
     * @return LengthAwarePaginator<int, ImpersonationSession>
     */
    public function sessions(AuditExplorer $explorer): LengthAwarePaginator
    {
        return $explorer->sessionsBy($this->agentId(), self::PER_PAGE);
    }

    /**
     * @return LengthAwarePaginator<int, RegistryChange>
     */
    public function settingChanges(AuditExplorer $explorer): LengthAwarePaginator
    {
        return $explorer->registryChangesBy($this->actorFilter(), self::PER_PAGE);
    }

    /**
     * Who was given access, taken away, or signed in — `28` §9.1's own sentence.
     *
     * ⚠️ **A THIRD STORE, AND IT DOES NOT WIDEN THE BOUNDARY EITHER** (741).
     * `staff_events` has no tenant at all, so it joins `impersonation_sessions`
     * and `registry_changes` on the same terms the docblock above sets out: the
     * question is answered from what already belongs to us, and `audit_log`'s
     * RLS policy is untouched.
     *
     * ⚠️ **Read through `StaffDirectory`, never `StaffEventRecord::query()`** —
     * decision 624 for the third time. That model is held to one service by a
     * chokepoint lint, and adding a query here would weaken a chokepoint to save
     * a line.
     *
     * @return LengthAwarePaginator<int, StaffEventRecord>
     */
    public function staffEvents(StaffDirectory $staff): LengthAwarePaginator
    {
        return $staff->eventsBy($this->actorFilter(), self::PER_PAGE);
    }

    public function render(AuditExplorer $explorer, StaffDirectory $staff): View
    {
        return view('livewire.admin.staff-activity', [
            'sessions' => $this->sessions($explorer),
            'settingChanges' => $this->settingChanges($explorer),
            'staffEvents' => $this->staffEvents($staff),
        ]);
    }

    /**
     * The typed reference as a user id, or null for "everyone".
     *
     * Non-numeric input is null rather than an error, which reads as lax and is
     * the safe direction here: null widens to every agent, so a typo shows more
     * than was asked for and never less. An input that silently *narrowed* on a
     * typo is how an auditor concludes somebody did nothing.
     */
    private function agentId(): ?int
    {
        $reference = trim($this->agent);

        return $reference !== '' && ctype_digit($reference) ? (int) $reference : null;
    }

    /**
     * How the same person is named in `registry_changes`.
     *
     * ⚠️ **`user:{id}` IS EXACT HERE, AND THE REASON IS NOT THE ONE THIS COMMENT
     * USED TO GIVE.** It described `audit_log`'s two vocabularies — a table this
     * component does not read at all. Both stores it *does* filter by actor,
     * `registry_changes` and `staff_events`, are platform-scoped and written only
     * from the console by a signed-in staff member acting as themselves, so
     * `user:{id}` is the only label either has ever held. Nothing edits platform
     * settings or grants a role from inside an impersonation session.
     *
     * ⚠️ **Do not "fix" this to search both prefixes.** Widening a filter to
     * match a label a table cannot contain is a lint matching nothing (256): it
     * would read as coverage of a case that does not exist, and the next person
     * to add an internal writer here would find the search already looking
     * correct. If one of these stores ever takes a `support:{id}` write, that is
     * the change that adds {@see StaffActor::labelsFor()} here, and it needs its
     * own test.
     *
     * The store where both labels genuinely appear is `audit_log`, read by
     * `AccountAudit` per account — see `StaffActor` for why its rows can never be
     * re-attributed.
     */
    private function actorFilter(): ?string
    {
        $id = $this->agentId();

        return $id === null ? null : StaffActor::OWN_PREFIX.$id;
    }
}
