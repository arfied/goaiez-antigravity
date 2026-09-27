<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Exceptions\GbpRequestFailed;
use App\Models\WhatsappAccountBinding;

/**
 * What we are billed for, against what we are using — 6779(d), closed as far as
 * detection goes and no further.
 *
 * ## The hole this exists for
 *
 * {@see GbpConnections::begin()} writes a `pending` row and sends the owner to
 * Zernio's consent screen. If they grant `business.manage` on their Google
 * listing and never come back — a closed tab, a dropped connection, a flow
 * abandoned at the location picker — **Zernio has a connected account and bills
 * for it every day**, and this application has a `pending` row that nothing ever
 * revisits. ⛔ **And it is invisible to our own ledger**, because
 * {@see ZernioSpend::connectedAccounts()} counts `gbp_account_bindings` and
 * there is no binding. That is 3297's exact sentence — *a money path with no
 * counter does not look uncapped, it looks free* — on the one vendor in `app/`
 * that bills per connected account per day.
 *
 * ## Why a sweep is affordable here when it was not for the other orphan probe
 *
 * 4887 declined to schedule `gbp:revoke-owed-grants --reconcile` because it
 * *"spends a tenancy switch per binding"*. This costs **one** vendor request and
 * two local reads, and the request itself is free: 4720 established that Zernio
 * bills per connected account per day and explicitly **not** per API call —
 * *"full API access … no per-feature pricing"*. So the usual objection to a
 * sweep does not apply, and 6614's reasoning about `profileOwnsAccount()`
 * carries across unchanged: a per-request meter on this vendor would count a
 * quantity whose price is zero.
 *
 * ## What it must never become
 *
 * ⛔ **NOTHING HERE REVOKES, DISCONNECTS, BINDS OR REPAIRS ANYTHING, AND THAT IS
 * THE STANDING ANSWER RATHER THAN THIS SLICE'S SCOPE** (4884, 4888(b), 6767).
 * *"A sweep acting on it disconnects a live customer's listing the first time a
 * filter is wrong"*, with no way back but asking the owner to consent again at
 * Zernio. An orphan here is an inference from an absence, and three states
 * arrive identically: an abandoned flow, an account connected in Zernio's own
 * console, and a redirect that has not landed yet (6766). **Detect, count,
 * surface. A human reads it and acts, off this application.**
 *
 * ⛔ **AND IT MUST NEVER BECOME THE METER.** {@see ZernioSpend} deliberately
 * counts bindings and never calls the vendor: `allowsNewAccount()` runs inside
 * {@see GbpConnections::begin()} on an owner's own click, and a ceiling that
 * asked a third party would refuse every connection on the platform during
 * their outage — a hard fail where rule 43's surviving half requires graceful
 * degradation (3294). The two numbers are supposed to be able to disagree; that
 * disagreement is the finding.
 *
 * ## Fails closed, and what that means for the sentence an operator reads
 *
 * ⚠️ **A VENDOR FAILURE PROPAGATES AND IS NEVER AN EMPTY REPORT.** *"Nothing is
 * unaccounted for"* is the one conclusion this must not reach on its own —
 * 4720's permanently-zero meter reached through a `catch`.
 */
final class ZernioReconciliation
{
    /**
     * ⚠️ **NO VENDOR CLIENT HERE, AND THAT IS A LINT RATHER THAN A STYLE**
     * (6904). `GbpTest` permits four files in `app/` to name a Google Business
     * client, on the reasoning that a caller holding one can call it with an
     * account reference it got from somewhere else.
     * {@see GbpConnections::connectedAccountsAtProvider()} is the door, it takes
     * no arguments, and this class goes through it.
     */
    public function __construct(
        private readonly GbpConnections $connections,
        private readonly ZernioSpend $spend,
    ) {}

    /**
     * Ask the vendor what is connected and diff it against what we hold.
     *
     * ⚠️ **THE VENDOR IS ASKED FIRST AND THE LOCAL READ HAPPENS AFTER**, so a
     * binding written while the request was in flight is present in the local
     * side of the diff. The error is then in the safe direction: a just-bound
     * account is *not* reported as an orphan, where the other ordering would
     * report one and point an operator at a connection that had just completed.
     *
     * @throws GbpRequestFailed when Zernio cannot be asked — never a clean report
     */
    public function run(): ZernioReconciliationReport
    {
        $listed = $this->connections->connectedAccountsAtProvider();

        // Through `GbpConnections` rather than through the model, so this class
        // never names `GbpAccountBinding` and `GbpTest`'s two-part lint on that
        // model keeps the allowlist it has (624's shape).
        $bindings = $this->connections->bindingsByAccountRef();
        $whatsappRefs = WhatsappAccountBinding::query()->pluck('account_ref')->flip();

        $orphanAccounts = [];
        $seen = [];

        foreach ($listed->accounts as $account) {
            $seen[$account->accountRef] = true;

            if ($bindings->has($account->accountRef)) {
                continue;
            }

            // A WhatsApp number bound through wave 839 is ours, not an orphan. Missing-at-provider detection below stays Google-only (WhatsApp bindings carry no business_id) — recorded as a follow-up.
            if ($whatsappRefs->has($account->accountRef)) {
                continue;
            }

            $orphanAccounts[] = $account;
        }

        $profileRefs = [];

        foreach ($orphanAccounts as $account) {
            if ($account->profileRef !== null) {
                $profileRefs[$account->profileRef] = true;
            }
        }

        /** @var array<string, int> $businesses */
        $businesses = $this->connections->businessesForProfiles(array_keys($profileRefs));

        $orphans = [];

        foreach ($orphanAccounts as $account) {
            $orphans[] = new ZernioOrphanedAccount(
                profileRef: $account->profileRef,
                businessId: $account->profileRef === null
                    ? null
                    : ($businesses[$account->profileRef] ?? null),
                platform: $account->platform,
            );
        }

        $missing = [];

        // ⚠️ **NOT IMPORTED, AND THE OMISSION IS DELIBERATE.** `GbpTest`'s note
        // on the revocation log says an import added only to name a class in a
        // docblock is *"exactly how an allowlist acquires an entry that later
        // reads as permission"*. The element type comes from
        // `bindingsByAccountRef()`'s own generic instead.
        foreach ($bindings as $accountRef => $binding) {
            if (! isset($seen[$accountRef])) {
                $missing[] = (int) $binding->business_id;
            }
        }

        $billed = $listed->billedCount();
        $bound = $bindings->count();

        return new ZernioReconciliationReport(
            billedAccounts: $billed,
            boundAccounts: $bound,
            unreadable: $listed->unreadable,
            orphans: $orphans,
            businessesBoundButNotConnected: array_values(array_unique($missing)),
            billedMonthCents: $this->spend->steadyStateMonthCents($billed),
            accountedMonthCents: $this->spend->steadyStateMonthCents($bound),
        );
    }
}
