<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\GbpRevocationOutcome;
use App\Exceptions\GbpRequestFailed;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\ZernioOrphanedAccount;
use App\Services\Gbp\ZernioReconciliation;
use App\Support\Admin\AdminAccess;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The Google grants a deleted tenant left behind — the visibility half of
 * decision 4888(a), and the presentation-only half of 4888(b).
 *
 * ## What 4888 said was still owed
 *
 * *"(a) Nothing surfaces an outstanding grant on a screen. The signal is two
 * console warnings and a row; an operator who does not read cron output learns
 * nothing, and this is the same gap `sending_health_windows` had before it had
 * a reader. (b) The pre-existing orphans are counted and not cleaned —
 * `--reconcile` names them and a human must act, and nobody has run it against
 * production."*
 *
 * This screen is that reader. It does not close (b) — 4884 is explicit that
 * closing it is a human act outside this application, at Zernio's own console
 * or by consenting again — it makes the count and the evidence behind it
 * something a person can actually find.
 *
 * ## Two lists, and only one of them offers an action
 *
 * **Owed** — `revocation_owed_at` is set. `TenantDeletion` recorded these
 * *inside* the transaction that destroyed the business (4880), so they are
 * known rather than inferred. An operator may retry one, per row, because a
 * retry here does exactly what `gbp:revoke-owed-grants` already does
 * unattended every night — the only difference is who pressed the button and
 * when.
 *
 * **Possibly orphaned** — {@see GbpConnections::bindingsWithNoSurvivingBusiness()},
 * built from a probe 4732 said could not be written as a set query and 4884
 * built anyway by asking one binding at a time from inside its own tenancy.
 * ⚠️ **IT RUNS ONLY WHEN AN OPERATOR ASKS FOR IT** — see
 * {@see self::findOrphans()} for what it costs and why that is not a rendering
 * preference.
 * ⛔ **NO ACTION IS OFFERED HERE, AND THAT IS DELIBERATE RATHER THAN AN
 * OMISSION.** 4884's own words: *"a sweep that revoked on it disconnects a
 * live customer's Google listing the first time a filter is wrong … a human
 * reads the count and decides."* This screen is that reading; the deciding
 * happens off it, because the evidence for this list is an inference and not
 * a record.
 *
 * ## The third list — 6779(d), and it is a different question from the other two
 *
 * ⚠️ **THE TWO LISTS ABOVE ARE BOTH ABOUT BUSINESSES THAT NO LONGER EXIST. THE
 * THIRD IS ABOUT ACCOUNTS WE ARE PAYING FOR RIGHT NOW** (6903), and confusing
 * them is the readable mistake this screen has to work to prevent. An owner who
 * presses **Connect**, grants `business.manage` at Zernio's consent screen and
 * never lands back on the redirect leaves a live grant, a connected account on
 * Zernio's invoice, and a `pending` row here that nothing revisits — and
 * `ZernioSpend::connectedAccounts()` counts *bindings*, so our own ledger cannot
 * see it. That is 3297's *a money path with no counter does not look uncapped,
 * it looks free*, on the one vendor that bills per connected account per day.
 *
 * ⛔ **IT OFFERS NO ACTION EITHER, FOR 4884's REASON AND NOT FOR SYMMETRY.**
 * Three states arrive here identically — an abandoned flow, an account connected
 * in Zernio's own console, and a redirect that has not landed yet (6766) — so
 * every row is an inference, and *"a sweep acting on it disconnects a live
 * customer's listing the first time a filter is wrong."*
 *
 * ⚠️ **AND IT RENDERS NO ACCOUNT REFERENCE, WHICH IS THE SAME DISCIPLINE 4884
 * APPLIES TO THE SECOND LIST**: a business number where we recorded one, a
 * profile reference where we did not, and never the value that decides whose
 * Google listing a call reaches.
 *
 * ## What the screen does not claim
 *
 * ⚠️ **"REVOKED" MEANS ZERNIO ACCEPTED THE REQUEST, NOT THAT THE GRANT IS
 * CONFIRMED GONE** — 4888(c)'s limit, carried onto every sentence this screen
 * renders. See {@see GbpRevocationOutcome::Revoked}.
 *
 * ## No tenant, no `AuditService`, and the log this screen reads instead
 *
 * Every business this screen describes has already been destroyed, so there is
 * no tenant to file an audit entry under — `SendingControls::haltPlatform()`'s
 * reasoning applies unchanged. What stands in is the append-only
 * `gbp_grant_revocation_attempts` log, `CredentialChange`'s shape:
 * platform-scoped, and the one place an operator's retry and the nightly sweep
 * leave the same kind of evidence.
 *
 * ⛔ **AND THIS CLASS REACHES THAT LOG ONLY THROUGH `GbpConnections`, WHICH IS
 * NOW A LINT RATHER THAN AN ARRANGEMENT** (5071). The table is named above in
 * prose rather than as a class, and that is deliberate twice over: an import
 * here would have to be permitted by `GbpTest`'s single-writer chokepoint, and
 * an allowlist entry added for a docblock is one that later reads as permission
 * to write. ⚠️ **A `{@see}` WITH THE FULLY-QUALIFIED NAME DOES NOT WORK EITHER**
 * — Pint's `fully_qualified_strict_types` shortens it and adds the import back,
 * silently, on the next `composer lint`. Found by running one.
 */
final class GbpGrantRevocations extends Component
{
    /**
     * The possibly-orphaned business references, once somebody has asked.
     *
     * ⛔ **NULL MEANS "NOT ASKED", NEVER "NONE FOUND"** — the two are rendered
     * as different sentences, because an empty list an operator did not request
     * reads as an all-clear nobody checked for.
     *
     * @var ?list<int>
     */
    public ?array $orphans = null;

    /**
     * The vendor reconciliation, once somebody has asked for it.
     *
     * ⛔ **NULL MEANS "NOT ASKED OR NOT ANSWERED", NEVER "NOTHING FOUND"** —
     * {@see self::$orphans}' rule, and here it carries a second job: a vendor
     * failure leaves this null and raises a toast, because *"nothing is
     * unaccounted for"* is the one conclusion a broken reconciliation must never
     * reach on its own (4720's permanently-zero meter, through a `catch`).
     *
     * ⚠️ **A PRIMITIVE ARRAY RATHER THAN THE REPORT OBJECT**, because a Livewire
     * property has to survive a round trip through JSON and back, and a readonly
     * value object does not. The shape is flattened once, here, so the view
     * never reaches into a service's return type.
     *
     * @var ?array{billed: int, bound: int, unreadable: int, orphanCents: int, orphans: list<array{profile: ?string, business: ?int, platform: ?string}>, boundNotConnected: list<int>}
     */
    public ?array $reconciliation = null;

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Run the orphan probe, once, because somebody pressed the button.
     *
     * ⛔ **THIS USED TO BE IN `render()` AND THAT MADE IT O(EVERY CONNECTED
     * LOCATION ON THE PLATFORM) PER PAGE VIEW AND PER LIVEWIRE ROUND TRIP**
     * (5076). {@see GbpConnections::bindingsWithNoSurvivingBusiness()} probes
     * every binding **without** a revocation stamp — which is every connected
     * location of every paying customer — and each probe is a
     * `Tenancy::actingAs()`, whose own docblock says *"for jobs, console
     * commands, and tests — not a way around the boundary in request code."*
     * 4887 declined to schedule this nightly on exactly that cost and then it
     * arrived on a screen, which is the more expensive of the two places to put
     * it: a cron runs it once a night, a page runs it every time anybody
     * presses anything.
     *
     * ⚠️ **THE OWED LIST IS A DIFFERENT MATTER AND STAYS IN `render()`.** Its
     * probe is bounded by the number of outstanding obligations — none, in the
     * state this screen exists to reach — and what it answers is load-bearing
     * rather than informational: see {@see GbpConnections::owedGrants()}.
     *
     * ⚠️ **DEDUPED, BECAUSE THIS IS A LIST OF BUSINESSES AND NOT OF BINDINGS**
     * (5077). The service returns one reference per orphaned row, which is what
     * `gbp:revoke-owed-grants --reconcile` counts and prints; a former tenant
     * with two orphaned locations would otherwise render *"Business #7"* twice,
     * under two identical `wire:key`s, and Livewire's morphing on duplicate keys
     * is undefined.
     */
    public function findOrphans(GbpConnections $connections): void
    {
        $this->authorize(AdminAccess::GATE);

        // `array_values()` rather than the collection's own `values()`, because
        // the property is a `list<int>` and only the native call proves it —
        // Larastan cannot see that `unique()` preserved a list.
        $this->orphans = array_values(
            $connections->bindingsWithNoSurvivingBusiness()->unique()->all(),
        );
    }

    /**
     * Ask Zernio what it is billing us for, and diff it against what we hold.
     *
     * ⚠️ **ON DEMAND, LIKE {@see self::findOrphans()}, THOUGH IT COSTS SOMETHING
     * COMPLETELY DIFFERENT.** That one spends a tenancy switch per binding;
     * this one spends a single vendor request, and 4720 established that Zernio
     * bills per connected account per day and **not** per API call, so the
     * request itself is free. It stays behind a button anyway for a reason that
     * is not cost: it reaches a third party, and a page that calls somebody
     * else's API on every render is one whose availability is theirs.
     *
     * ⛔ **A FAILURE IS SHOWN AS A FAILURE AND NEVER AS AN EMPTY RESULT.**
     * `GbpRequestFailed` covers the vendor being down, our platform key being
     * wrong, and `gbp.zernio_enabled` being off — and every one of those means
     * *we do not know*, which on this screen is a different sentence from
     * *everything is accounted for*.
     *
     * ⚠️ **THE VENDOR'S REASON IS NOT RELAYED**, on `Account\Connections`' own
     * rule: it is a machine-readable code for our logs, not a sentence for a
     * person, and repeating it invites somebody to act on a vendor's vocabulary.
     */
    public function reconcile(ZernioReconciliation $reconciliation): void
    {
        $this->authorize(AdminAccess::GATE);

        try {
            $report = $reconciliation->run();
        } catch (GbpRequestFailed) {
            $this->reconciliation = null;

            Toaster::error('Zernio could not be asked just now, so nothing was checked. Nothing here has changed.');

            return;
        }

        $this->reconciliation = [
            'billed' => $report->billedAccounts,
            'bound' => $report->boundAccounts,
            'unreadable' => $report->unreadable,
            'orphanCents' => $report->orphanMonthCents(),
            'orphans' => array_map(
                static fn (ZernioOrphanedAccount $orphan): array => [
                    'profile' => $orphan->profileRef,
                    'business' => $orphan->businessId,
                    'platform' => $orphan->platform,
                ],
                $report->orphans,
            ),
            'boundNotConnected' => $report->businessesBoundButNotConnected,
        ];
    }

    /**
     * Retry exactly one owed grant.
     *
     * ⚠️ **PER ROW.** The binding id, not a business id — see
     * `GbpConnections::retryRevocation()`'s own docblock on why a business
     * with two owed locations must be able to retry one without touching the
     * other.
     */
    public function retry(int $bindingId, GbpConnections $connections): void
    {
        $this->authorize(AdminAccess::GATE);

        // ⚠️ **THE STALE-STAMP REFUSAL IS NOT HERE, AND THAT IS DELIBERATE**
        // (5074). The view withholds the button on a row whose business still
        // exists, which is a rendering decision and therefore not a refusal —
        // this method takes a binding id from the browser. `retryRevocation()`
        // refuses it at the service and the message arrives on the same
        // `InvalidArgumentException` path as every other refusal below.

        try {
            $revoked = $connections->retryRevocation($bindingId, $this->actor());
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        if ($revoked) {
            // Outcome language, and the honest one — see the class docblock.
            // Not "grant revoked": that states a fact about Google's listing
            // this application has not verified.
            Toaster::success('Zernio accepted the request. This grant is no longer recorded as owed.');

            return;
        }

        Toaster::error('Zernio refused the request. Nothing changed here, and this is tried again tonight.');
    }

    public function render(GbpConnections $connections): View
    {
        $this->authorize(AdminAccess::GATE);

        return view('livewire.admin.gbp-grant-revocations', [
            'owed' => $connections->owedGrants(),
        ]);
    }

    /**
     * `audit_log`'s vocabulary — `SendingControls::actor()`'s shape, carried
     * onto a table that has no tenant to write an audit entry into at all.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
