<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Enums\SupportSenderStanding;
use App\Models\Business;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\User;
use App\Models\WizardProgress;
use App\Services\AuditService;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\Subscriptions;
use App\Services\Billing\TrialEligibility;
use App\Services\Legal\BaaRecords;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Accounts\AccountSnapshot;
use App\Support\Tenancy;

/**
 * The one way the support console finds and reads a customer's account.
 *
 * `28` §9.3's Account 360, reached by naming one customer rather than by
 * picking one off a list — see decision 800 for why the list is refused a
 * fourth time, and what would change that answer.
 *
 * ## Two ways in, and the second one is why this class exists
 *
 * **By account number**, through `Tenancy::actingAs()`. That is 569's pattern
 * and it needs nothing new: setting `app.business_id` makes `tenant_isolation`
 * admit exactly that row and nothing else becomes visible.
 *
 * **By the owner's email address**, through `Tenancy::actingAsUser()` and the
 * `owner_lookup` policy. ⚠️ **This is not a convenience.** Four staff screens
 * now open an account by typing its number, and *nothing in this application
 * has ever shown that number to anyone who could supply it* — no owner-facing
 * view renders it, and `28` §9.3's "one click from Account 360" assumes a
 * ticketing system that does not exist. So the door built four times was
 * unopenable in practice, and an agent with a customer on the phone had no way
 * in at all. An email address is what they actually hold, and "which business
 * does this person own" is the exact question `owner_lookup` was written to
 * answer — the same resolution circularity `ResolveTenant` breaks, one caller
 * further out. It is a resolution path, not a list: it discloses one person's
 * own accounts and cannot be walked.
 *
 * ## Reading is recorded, and that is what makes this different from a leak
 *
 * A resolved lookup writes `business.viewed_by_staff` into that tenant's own
 * `audit_log` — the same action name `PhiTenants` files, so the audit explorer
 * of slice 2 shows both under one search rather than two vocabularies for one
 * event (622). ⚠️ **`Support\Accounts` wrote nothing before this**: it resolved
 * a business, put its name on screen, and left no trace anywhere, so staff
 * could walk the id space unobserved. Every other cross-tenant staff read in
 * this codebase is audited; that one was the exception, and it was the one on
 * the least-privileged gate.
 *
 * ⚠️ **The typed reference never reaches the log.** The metadata records *how*
 * the account was matched and not *what was typed*, because an email address
 * is personal data and `audit_log` is append-only forever — a search box
 * spilling into an immutable table is a retention decision nobody made.
 */
final class AccountDirectory
{
    /** Matched by the account number an agent typed. */
    public const MATCHED_BY_NUMBER = 'number';

    /** Matched by the email address of the person who owns the account. */
    public const MATCHED_BY_OWNER_EMAIL = 'owner_email';

    /**
     * ⚠️ FOUR COLLABORATORS RATHER THAN FOUR QUERIES, AND THAT IS DECISION
     * 624 FOR THE FIFTH TIME. `subscriptions` and `baa_records` are each held
     * to one service by an ArchitectureTest lint that names *reading*, so a
     * `Subscription::query()` here would have been a chokepoint weakened by a
     * screen — reasonable on its own diff, and invisible as a security change.
     * When a lint refuses a new reader, the reader moves behind the service and
     * the lint is untouched.
     */
    public function __construct(
        private readonly AuditService $audit,
        private readonly BaaRecords $baaRecords,
        private readonly Subscriptions $subscriptions,
        // ⚠️ THE FIFTH AND SIXTH INSTANCES OF THE SAME RULE. Both stop columns
        // are held to their own service by a lint, and every file on those
        // allowlists is one that can *write* a stop — so a screen that only
        // reads asks the writer rather than joining the list.
        private readonly TenantPause $pause,
        private readonly TenantSuspension $suspension,
        // `CreditLedger::balance()` already refuses to run with no tenant
        // established (`Tenancy::idOrFail()`), so composing it here is safe
        // only because the call below sits inside `Tenancy::actingAs()` — see
        // `snapshot()`.
        private readonly CreditLedger $credits,
        // ⚠️ THE SEVENTH INSTANCE, AND THE ONE WITH THE LEAST TO GUARD. Unlike
        // its neighbours `trial_claims` is behind no chokepoint lint, because it
        // holds nothing but keyed hashes and account numbers. The service is
        // composed here for the ordinary reason instead: the *rule* for reading
        // a claim — the earlier claimant keeps the entitlement, an absent origin
        // is counted in no burst — must have one implementation, and a screen
        // rebuilding it from the table would be a second one.
        private readonly TrialEligibility $trials,
    ) {}

    /**
     * Find the account this reference names, and record that staff opened it.
     *
     * Returns the business id rather than the model: the caller holds it across
     * Livewire requests, and a re-read under tenancy on each one is the shape
     * `PhiTenants` settled on — an action must not run against a snapshot taken
     * in another tab.
     *
     * ⚠️ ONE ENTRY PER RESOLVED LOOKUP, NOT PER RENDER. Livewire re-renders on
     * every property update, so auditing the read path would file a row per
     * keystroke and make the log unreadable, which is its own kind of
     * unaudited. And a miss writes nothing, because there is no tenant to file
     * it under — "no account matches that" discloses nothing about an account
     * that does not exist.
     *
     * ⚠️ THE AUDIT WRITE IS NOT WRAPPED, AND THE ORDER IS LOAD-BEARING. An
     * unwritable `audit_log` fails the whole lookup instead of showing the
     * account, which is `PhiTenants::lookUp()`'s reasoning: catching it would
     * put a tenant's name and owner on screen with nothing recording the read,
     * which is the single thing the entry exists to prevent.
     */
    public function open(string $reference, string $actor): ?int
    {
        $match = $this->resolve($reference);

        if ($match === null) {
            return null;
        }

        [$business, $matchedBy] = $match;

        $id = (int) $business->id;

        Tenancy::actingAs($id, fn (): mixed => $this->audit->record(
            'business.viewed_by_staff',
            $actor,
            $business,
            ['surface' => 'account_360', 'matched_by' => $matchedBy],
        ));

        return $id;
    }

    /**
     * Whether this account can spend what an operator is about to give it (3926).
     *
     * ⚠️ **THE SAME QUESTION {@see self::snapshot()} PUTS ON THE RAIL, ASKED ON
     * ITS OWN BECAUSE A TOAST IS COMPOSED BEFORE THE NEXT RENDER.** One read
     * rather than a whole snapshot: the grant action needs one boolean, and
     * rebuilding six balances, every location and the trial verdict to get it
     * would be a page load charged to a sentence.
     *
     * ⚠️ **THE ANSWER IS `Subscriptions::isEntitled()` ON BOTH PATHS**, so the
     * toast, the rail and `CreditLedger`'s own gate cannot disagree — and it is
     * asked inside the tenancy for the reason `snapshot()` gives.
     */
    public function creditIsSpendable(int $businessId): bool
    {
        return Tenancy::actingAs($businessId, function () use ($businessId): bool {
            $business = Business::query()->find($businessId);

            // No row is no answer. Fail *open* on the wording specifically: this
            // decides a sentence, never a refusal, and telling an operator the
            // credit is waiting when the account has vanished under them is the
            // less useful of the two wrong answers.
            return ! $business instanceof Business || $this->subscriptions->isEntitled($business);
        });
    }

    /**
     * Everything Account 360 v1 shows about one account.
     *
     * Every read runs inside that business's own tenancy, so the global scope
     * and row-level security both bound whatever id arrives here — the audit
     * row on open() is what says a human asked for it, not what makes it safe.
     */
    public function snapshot(int $businessId): ?AccountSnapshot
    {
        return Tenancy::actingAs($businessId, function () use ($businessId): ?AccountSnapshot {
            $business = Business::query()->with('owner')->find($businessId);

            if (! $business instanceof Business) {
                return null;
            }

            $owner = $business->owner;
            $subscription = $this->subscriptions->for($business);

            return new AccountSnapshot(
                businessId: $businessId,
                name: $business->name,
                ownerName: $owner instanceof User ? $owner->name : null,
                ownerEmail: $owner instanceof User ? $owner->email : null,
                classification: $business->data_classification,
                createdAt: $business->created_at,
                subscription: $subscription,
                locations: Location::query()->orderBy('id')->get(),
                integrations: OauthConnection::query()->orderBy('id')->get(),
                setup: WizardProgress::query()->latest('id')->first(),
                baaInForce: $this->baaRecords->isExecutedFor($business),
                paused: $this->pause->isPaused($business),
                pauseReason: $this->pause->reasonFor($business),
                suspended: $this->suspension->isSuspended($business),
                suspensionReason: $this->suspension->reasonFor($business),
                suspendedBy: $this->suspension->suspendedBy($business),
                // ⚠️ ALL SIX NOW, AND THE REASON THE OTHER FIVE WERE ABSENT IS
                // THE REASON THEY ARE HERE (3426, 3437 item 3). This read the SMS
                // total alone, because `CreditGrants` granted texts and "a
                // read-only figure nobody can act on is the next support ticket".
                // An operator can now grant any of the three, so the screen that
                // offers the control shows what the control moves.
                //
                // ⛔ SIX FIGURES AND NO SEVENTH (3428). Nothing adds any two of
                // them: they are counted in two different units, and the sum is a
                // number that renders perfectly and means nothing.
                creditBalances: $this->creditBalances(),
                // ⛔ WHAT THE SIX FIGURES ABOVE DO NOT SAY (3926). Since 3441 the
                // top-up pool — the one a support grant lands in — is filtered out
                // of the draw order while the plan is inactive, so a balance card
                // showing 750 emails on a cancelled account was showing a figure
                // nothing could spend.
                //
                // ⚠️ ASKED INSIDE THE TENANCY, AND IT HAS TO BE. `isEntitled()`
                // reads a tenant-scoped row and fails *open* when there is none,
                // so asking it outside this closure would answer "spendable" for
                // every account in the schema — a deliberate fail-open turned into
                // a fail-open falsehood, on the one screen that can put credit into
                // the pool it describes.
                creditIsSpendable: $this->subscriptions->isEntitled($business),
                // ⚠️ ASKED INSIDE THE TENANCY WITH EVERYTHING ELSE, THOUGH IT
                // NEED NOT BE. `trial_claims` carries no tenant scope by design,
                // so this one read would answer correctly outside the closure —
                // and doing it here anyway keeps one rule for the whole method
                // rather than an exception the next reader has to re-derive.
                trialGrant: $this->trials->verdictFor($business),
                // ⛔ THE DATE THE ROW CANNOT CARRY (9332). A `pending_checkout`
                // row has a null `trial_ends_at` by construction — both writers
                // of that column require a card — and since 2026-08-25 that is
                // precisely the account whose trial can have ended. Without this
                // an operator reads "Pending checkout", no date, "their plan is
                // not running", and has nothing to tell the person on the phone.
                //
                // ⚠️ NULL FOR EVERYBODY NOT ON THE NO-CARD TRIAL, so the screen
                // never invents a clock for a paying tenant. The status test is
                // the service's, not this method's — one rule, one place.
                noCardTrialEndsAt: $this->subscriptions->noCardTrialEndsAt($business, $subscription),
            );
        });
    }

    /**
     * Every product's two pools, in each product's own ledger unit.
     *
     * ⚠️ **THE LEDGER IS ASKED SIX TIMES AND NOT ONCE.** `CreditLedger::balance()`
     * requires a product and takes an optional pool, and both parameters are
     * deliberate (3419, 3428) — there is no method that returns them all, because
     * a method that did would be one refactor away from summing them. Six reads of
     * a head row on an indexed `(business_id, product, pool, id)` is the cost of
     * that, and this method is called once per render of one account.
     *
     * ⚠️ **CALLED ONLY FROM INSIDE `Tenancy::actingAs()`** — `balance()` opens
     * with `Tenancy::idOrFail()`, so this is private and has exactly one caller
     * rather than being a convenience anything could reach.
     *
     * @return array<string, array{monthly: int, top_up: int}>
     */
    private function creditBalances(): array
    {
        $balances = [];

        foreach (CreditProduct::cases() as $product) {
            $balances[$product->value] = [
                'monthly' => $this->credits->balance($product, CreditPool::Monthly),
                'top_up' => $this->credits->balance($product, CreditPool::TopUp),
            ];
        }

        return $balances;
    }

    /**
     * The business itself, for the one caller that needs the model.
     *
     * `Impersonation::start()` takes a `Business`, and it must be the row as it
     * stands now rather than as it stood when the agent found it.
     */
    public function business(int $businessId): ?Business
    {
        return Tenancy::actingAs(
            $businessId,
            fn (): ?Business => Business::query()->find($businessId),
        );
    }

    /**
     * Whose account an inbound support email is, by the address it came from
     * (T176 P24).
     *
     * ⚠️ **A RESOLUTION, NOT AN OPEN, AND THE DIFFERENCE IS THE AUDIT ROW.**
     * {@see self::open()} files `business.viewed_by_staff` because nothing
     * invited staff into that account. Nobody has viewed anything here: the
     * tenant wrote to *us*, and `SupportDesk::record()` files
     * `support.ticket_raised` in their own log for the same act. Filing a
     * surveillance read as well would put a row saying an agent opened the
     * account into the log of every tenant who ever emailed support, which is
     * the entry that stops meaning anything.
     *
     * ⚠️ **IT LIVES HERE RATHER THAN IN THE MAIL POLLER BECAUSE OF THE LINT
     * ABOVE IT.** `Tenancy::actingAsUser()` is held to this one class by
     * `StaffTest` — *"a second caller is reasonable on its own diff and does not
     * read as a security change"* — and a poller resolving owners for itself
     * would be exactly that diff. One method here costs less than an allowlist
     * entry there.
     *
     * ⚠️ **THE OWNER ONLY.** A member of the tenant's staff who is not the owner
     * resolves to nothing, and their mail stays in the mailbox for a person —
     * {@see SupportInbox}'s own instruction. That is a stated limit rather than
     * a hidden one, and it is the same limit `byOwnerEmail()` already carries for
     * the console.
     *
     * ⛔ **IT TAKES THE WHOLE MAIL RATHER THAN THE ADDRESS, AND THAT IS THE
     * FIX FOR 4606 RATHER THAN A TIDINESS** (4607). `From` is written by the
     * sender, and this method maps it to a `business_id` *and* an
     * `author_user_id` — so while it accepted a bare string, anybody who knew a
     * tenant owner's email address could post text into that tenant's support
     * thread attributed to the owner. **A `FetchedSupportMail` is the only thing
     * that carries the provider's verdict on that address**, and it can only be
     * built by the transport that read the headers, so there is no longer a call
     * shape that resolves an unauthenticated sender. A `bool` second parameter
     * would have been the same check and a caller could pass `true`.
     *
     * @return array{business_id: int, user_id: int}|null
     */
    public function accountOfSender(FetchedSupportMail $mail): ?array
    {
        // ⛔ **THE PROVIDER'S VERDICT, NOT OURS, AND IT IS ASKED FIRST.** See
        // `SupportMailbox::senderIsAuthenticated()` for what it means and for
        // what it is not proven against.
        if (! $mail->senderIsAuthenticated) {
            return null;
        }

        $email = trim($mail->fromAddress);

        if ($email === '') {
            return null;
        }

        $business = $this->byOwnerEmail($email);

        if (! $business instanceof Business) {
            return null;
        }

        return ['business_id' => (int) $business->id, 'user_id' => (int) $business->owner_user_id];
    }

    /**
     * What the mailbox knows about a sender, for the tally and for nothing else
     * (4645).
     *
     * ⛔ **IT RETURNS NO TENANT, NO AUTHOR AND NO ADDRESS, AND THAT IS THE
     * WHOLE OF WHY IT IS SAFE TO EXIST BESIDE {@see self::accountOfSender()}.**
     * 4606 was a sender-written `From` resolving a `business_id` *and* an
     * `author_user_id`; 4607's fix was to make the routing call take a
     * {@see FetchedSupportMail} so no call shape could skip the provider's
     * verdict. **Neither is touched here.** This answers a question with a
     * {@see SupportSenderStanding} in it — four cases, none of them an id — and
     * a `Spoofed` message is routed exactly as far as a stranger's.
     * ⚠️ **DO NOT MAKE THIS RETURN THE BUSINESS.** The moment it does it is
     * 4606 again, with a tally as the excuse.
     *
     * ⚠️ **IT IS A TOTAL FUNCTION OF THE TWO FACTS AND DEPENDS ON NO CALL
     * ORDER.** The poller asks it only after `accountOfSender()` has returned
     * null, so it will never observe `Routed` in practice — but a version that
     * *assumed* that would be a method whose correctness lives in its caller,
     * which is 398's shape with a counter attached. `SupportMailPollTest` pins
     * the agreement between the two methods rather than leaving it to these two
     * docblocks.
     *
     * ⚠️ **THE OWNER LOOKUP RUNS FOR AN UNAUTHENTICATED SENDER, WHICH READS AS
     * A WIDENING AND IS NOT ONE.** {@see FetchedSupportMail} already sanctions
     * exactly this comparison in exactly this class — *"it exists for one
     * comparison — is this an account owner we know? — performed in memory,
     * after which the id is kept and the address is dropped"*. Here not even the
     * id is kept.
     */
    public function senderStanding(FetchedSupportMail $mail): SupportSenderStanding
    {
        $namesAnOwner = $this->byOwnerEmail(trim($mail->fromAddress)) instanceof Business;

        if (! $mail->senderIsAuthenticated) {
            return $namesAnOwner
                ? SupportSenderStanding::Spoofed
                : SupportSenderStanding::Unauthenticated;
        }

        return $namesAnOwner
            ? SupportSenderStanding::Routed
            : SupportSenderStanding::Unrecognised;
    }

    /**
     * What a typed reference resolves to, and how.
     *
     * @return array{0: Business, 1: string}|null
     */
    private function resolve(string $reference): ?array
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        if (ctype_digit($reference)) {
            $business = $this->business((int) $reference);

            return $business instanceof Business
                ? [$business, self::MATCHED_BY_NUMBER]
                : null;
        }

        $business = $this->byOwnerEmail($reference);

        return $business instanceof Business
            ? [$business, self::MATCHED_BY_OWNER_EMAIL]
            : null;
    }

    /**
     * The account owned by the person at this address.
     *
     * ⚠️ TWO STEPS, AND THE FIRST ONE IS NOT THE BOUNDARY. `users` carries no
     * tenant and no row-level security — internal staff belong to no business,
     * deliberately — so finding the person is an ordinary query and always was.
     * `businesses` is the guarded table, and it is guarded here by
     * `owner_lookup` alone: with `app.user_id` set to that person and no tenant
     * established, the database admits the rows they own and refuses every
     * other. `withoutGlobalScopes()` is what lets the query reach the policy at
     * all, and it is the *application* scope being dropped, never the database
     * one — see the ArchitectureTest lint that enumerates every file allowed to
     * do it, and answers why this is a fifth resolution path rather than the
     * list that lint refuses.
     *
     * ⚠️ CASE-FOLDED, WHICH COSTS THE INDEX AND IS WORTH IT. Fortify stores an
     * address as typed, so an exact match answers "no account" for a customer
     * who capitalised their own name — a false negative on the screen whose
     * entire job is to find them, and one nothing on it could explain. `users`
     * is the smallest table in this schema.
     *
     * ⚠️ THE PREDICATE IS NOT REDUNDANT WITH THE POLICY, AND THE CASE THAT
     * PROVES IT IS THE ONE NOBODY PICTURES. In an ordinary support session no
     * tenant is established, so `owner_lookup` alone applies and the database
     * would answer correctly with no `where` at all. But decision 621's staff
     * member *also owns a business*, and `ResolveTenant` establishes it — so
     * `tenant_isolation` is live too, RLS policies are permissive and **OR'd**,
     * and the agent's own row is as legitimately visible as the customer's.
     * `orderBy('id')->first()` would then hand back whichever was created
     * first, and an agent searching for a customer would open their own
     * account. The database cannot refuse that; only this line can.
     *
     * ⚠️ AND IT RESOLVES ONE ACCOUNT, NOT A SET. A person may own more than one
     * business, and the console shows the first by id rather than picking a
     * "primary" it has no basis to choose. That is a stated limit rather than a
     * hidden one: `ResolveTenant` does exactly the same thing for the owner's
     * own session, so a second business is already invisible to its own owner,
     * and inventing a rule here would be inventing it for the product.
     */
    private function byOwnerEmail(string $email): ?Business
    {
        $email = mb_strtolower($email);

        $userId = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->value('id');

        if (! is_int($userId)) {
            return null;
        }

        return Tenancy::actingAsUser($userId, fn (): ?Business => Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->orderBy('id')
            ->first());
    }
}
