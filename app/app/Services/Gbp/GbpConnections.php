<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Contracts\GbpClient;
use App\Enums\GbpConnectionStatus;
use App\Enums\GbpProvider;
use App\Enums\GbpRevocationOutcome;
use App\Enums\ImpersonationCapability;
use App\Enums\OperatorAlertKind;
use App\Exceptions\GbpConnectionRefused;
use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Http\Controllers\Gbp\GbpConnectController;
use App\Jobs\Reviews\SyncGoogleReviewsJob;
use App\Models\Business;
use App\Models\GbpAccountBinding;
use App\Models\GbpConnection;
use App\Models\GbpGrantRevocationAttempt;
use App\Models\GbpProfileBinding;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Impersonation\Impersonation;
use App\Services\Ops\OperatorAlerts;
use App\Services\Tenant\TenantDeletion;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The one place a location is bound to a Google Business account.
 *
 * ⚠️ **THIS IS DECISION 531'S STORE, AND THE REASON IT HAS TO BE A CHOKEPOINT IS
 * IN `ZernioGbpClient`'s OWN DOCBLOCK**: `$accountRef` is opaque, and nothing in
 * that client can tell whether an account id belongs to the tenant it was called
 * for. Every guard that makes a read safe therefore lives here, on the write
 * side. A second writer would not be a style problem — it would be a second
 * place an account ref can enter, and the ref is the whole boundary.
 *
 * ## What the tenant boundary actually rests on
 *
 * Four layers, and the fourth is the vendor's:
 *
 *   the global scope   `GbpConnection` is `BelongsToTenant`, so a query with no
 *                      tenant in context throws rather than returning rows.
 *   RLS                `gbp_connections` is `ENABLE`+`FORCE`d, which catches the
 *                      forgotten filter the scope would have applied.
 *   the signed callback the account ref arrives on a redirect from Zernio, who
 *                      carry no state of ours. See
 *                      {@see GbpConnectController}.
 *   the profile filter `GET /v1/accounts?profileId=…` — whether the account in
 *                      that callback is on this business's profile at all.
 *                      {@see complete()}.
 *
 * ⛔ **THE FOURTH IS NEW AT 6603 AND THIS BLOCK SAID IT COULD NOT EXIST —
 * CORRECTED 2026-08-21.** It read *"Three layers, and none of them is the
 * vendor's … **Zernio's own API offers no fourth layer and must not be mistaken
 * for one.** Our key is a platform key: `GET /v1/accounts` returns every account
 * every tenant has connected, in one list. Nothing in this application may
 * render that list, and nothing here reads it."*
 *
 * ⚠️ **THE OBSERVATION WAS RIGHT AND THE CONCLUSION DID NOT FOLLOW, WHICH IS
 * WHY BOTH ARE KEPT.** The *unfiltered* list is exactly as described and is
 * still forbidden — it is every tenant's accounts under our platform key, and
 * rendering it anywhere would be the disclosure this paragraph was written to
 * prevent. But the endpoint takes `profileId`, a profile belongs to exactly one
 * business, and Zernio's own multi-tenant guide calls a profile *"the tenant
 * boundary"*. **Asking a scoped question of that endpoint is not the same act as
 * reading the list**, and the difference is one query parameter.
 *
 * ⛔ **AND "STILL FORBIDDEN" IS NOW TOO BROAD BY ONE WORD — CORRECTED
 * 2026-08-21 (6907).** The clause above reads *"the **unfiltered** list is
 * exactly as described and is still forbidden … rendering it anywhere would be
 * the disclosure this paragraph was written to prevent"*, and its two halves
 * were doing different jobs. **The rule that survives is the second one:
 * the unfiltered list may not be RENDERED.** {@see connectedAccountsAtProvider()}
 * reads it — every account connected under our platform key, no `profileId`,
 * no `platform` — because *what are we being billed for?* cannot be asked any
 * other way: a scoped call returns only accounts we already knew to look for,
 * which answers the question by assuming it away (6779(d)). What comes back is
 * diffed against `gbp_account_bindings` and reduced to a count, a business
 * number and a profile reference; **no account id and no vendor-supplied name
 * reaches a screen**, which is 4884's discipline and is the thing the paragraph
 * above is actually protecting.
 *
 * ⚠️ **THE PATTERN IS THE SAME ONE 6606 CORRECTED AND IT IS WORTH NOTICING
 * TWICE**: a true sentence about a real hazard, generalised one step, becomes a
 * claim that stops the next person asking. 6606's cost was a tenant boundary
 * resting on a browser value for months; this one's would have been a money
 * path with no counter.
 *
 * ⚠️ **AND THE COST OF THE CONCLUSION WAS 6491.** Believing no vendor-side check
 * was available is what left the callback resting on `profileId` — a value the
 * browser supplies — for months, behind a docblock in
 * {@see GbpConnectController} claiming that check survived a signature the owner
 * held. `Nothing here reads it` was true when written and is the sentence that
 * stopped anybody asking whether it should.
 *
 * ## The profile, and why its name carries nothing about the tenant
 *
 * Zernio groups accounts under a *profile* and its connect flow requires one, so
 * each business gets exactly one. Its name is derived from the business id and
 * nothing else — not the business's own name — because the name is what we hand
 * a subprocessor, and `docs/SUBPROCESSOR-INVENTORY.md` describes what we send
 * them as our key and an opaque reference. A tenant's trading name would widen
 * that row for no capability at all: nobody here reads the profile back by name
 * except this class, matching what it wrote.
 */
final class GbpConnections
{
    /**
     * `audit_log`'s vocabulary, carried onto a table that has no tenant to
     * write one into — see {@see GbpGrantRevocationAttempt}. The nightly sweep
     * and the immediate revocation `TenantDeletion` attempts at the moment of
     * erasure both write this; an operator's own retry from the Ops screen
     * writes `user:<id>` instead.
     */
    public const SYSTEM_ACTOR = 'system';

    /**
     * `audit_log`'s name for a Google account binding changing hands.
     *
     * ⛔ **THE SUBJECT IS THE BINDING AND NOT THE CONNECTION, WHICH IS THE WHOLE
     * REASON THIS NAME HAD TO BE MINTED** (6616). `gbp.connected` is already
     * recorded on this path and it is about the `gbp_connections` row the
     * acquiring tenant owns — a row that is *new* to them and says nothing
     * about anyone else. The event with no name was the other half:
     * {@see bindAccount()} is an `updateOrCreate` keyed on the unique
     * `account_ref`, so the *surviving* `gbp_account_bindings` row has its
     * `business_id` rewritten in place, and a Google account that belonged to
     * one business now belongs to another with nothing anywhere recording that
     * it moved.
     *
     * ⚠️ **WHY `binding_moved` AND NOT THE THREE ALTERNATIVES.** `moved` is the
     * verb 5073 and 6616 both already use in prose for this exact act, so an
     * operator grepping the incident record for the words the decision log uses
     * finds the code. `rebound` was refused because it reads as the past tense
     * of *re-bind*, which is overwhelmingly the **legitimate and unremarkable**
     * case — the same business connecting the same account again — and that
     * case is deliberately **not** recorded here; a name that describes the
     * common harmless act would make the rare consequential one invisible in a
     * filter. `reassigned` was refused as vaguer and because no other action in
     * this vocabulary uses *assign*. `stolen`/`hijacked` were refused outright:
     * 5073 argues at length that the move is **correct** for a resubscribed
     * owner, a franchise or a sold location, and an action name that presumes
     * an attack would misreport the ordinary case every time it fired.
     *
     * ⛔ **THE RECORD LANDS IN THE ACQUIRING TENANT'S LOG AND ONLY THERE, AND
     * THAT IS A LIMITATION RATHER THAN A CHOICE.** {@see AuditService::record()}
     * opens with `Tenancy::idOrFail()` and `audit_log` is `BelongsToTenant` with
     * row-level security, so the losing business gets no entry in its own log.
     * Writing one would mean `Tenancy::actingAs($existing->business_id, …)`, and
     * both halves of that fail: the commonest legitimate move is 5073's, where
     * the losing business has been **erased** and the tenancy cannot be
     * established at all; and where it can, it would attribute an action to a
     * tenant that took none. One record, in the tenancy that acted, naming both
     * sides — which is what an auditor reading the platform's log needs, and is
     * why both ids are in the metadata rather than only the new one.
     */
    public const BINDING_MOVED = 'gbp.binding_moved';

    /**
     * `audit_log`'s name for the vendor confirming, unprompted, a mapping the
     * browser proposed.
     *
     * ⚠️ **THE PROVENANCE RECORD, AND THE REASON THE `account.connected`
     * WEBHOOK IS WORTH HANDLING AT ALL** (6615, 6765). Everything else on the
     * connect path arrives through a browser we do not control:
     * {@see GbpConnectController} spends four layers establishing that the
     * `accountId` in a redirect is not a lie. This event arrives
     * server-to-server, HMAC-signed, carrying `accountId` **and** `profileId`
     * as required fields, and Zernio's own multi-tenant guide calls its payload
     * *"exactly the mapping you want to persist"*. When it agrees with what we
     * already wrote, that agreement is worth a row: it is the only evidence in
     * this system that a binding was corroborated by something other than the
     * party that asked for it.
     */
    public const BINDING_CONFIRMED = 'gbp.binding_confirmed';

    /**
     * `audit_log`'s name for the vendor contradicting a binding we hold.
     *
     * ⛔ **THIS IS A DETECTOR AND DELIBERATELY NOT A CORRECTION** (6767). It
     * fires when Zernio reports the account bound to this location as living on
     * a **different profile** — which means our record of who owns that Google
     * listing is wrong, and the vendor is the authority on the question. The
     * tempting next line is to disconnect or re-point the connection, and 4884
     * is the standing answer to that temptation: acting unattended on an
     * inference disconnects a live customer's Google Business Profile the first
     * time a filter is wrong, with no way back but asking the owner to consent
     * again. **A human reads the record and acts.**
     *
     * ⛔ **"AND NOTHING SURFACES IT ON A SCREEN, WHICH IS 4888(a) UNCHANGED"
     * WAS TRUE AND IS NOT — CORRECTED 2026-08-21 (7200–7219).** It continued
     * *"an `OperatorAlertKind` case is what this wants and minting one was out
     * of scope for the slice that found it — see 6768"*, and that is now built:
     * {@see self::ringMismatch()} raises
     * {@see OperatorAlertKind::GbpBindingMismatched}, which reaches the
     * operator's board, the alert email and the alert text.
     *
     * ⚠️ **WHAT SURVIVES OF THE OLD SENTENCE IS THE PARAGRAPH ABOVE IT.** A bell
     * is not a correction: this constant still names a **record**, nothing acts
     * on it, and 6767's ruling is untouched by anything ringing.
     */
    public const BINDING_MISMATCHED = 'gbp.binding_mismatched';

    /**
     * `audit_log`'s actor for something Zernio told us without being asked.
     *
     * Matches the string {@see recordExternalDisconnect()} already passes for
     * the sibling event, so the two account-lifecycle webhooks read as one
     * actor in the record rather than as two subsystems.
     */
    public const WEBHOOK_ACTOR = 'system:zernio_webhook';

    /**
     * How much of a vendor failure code reaches {@see GbpGrantRevocationAttempt}.
     *
     * ⛔ **`reason` IS A `varchar(255)` AND THE VALUE IN IT IS WRITTEN BY
     * ZERNIO** (5072). `GbpRequestFailed::from()` takes it from the response's
     * `code`, which is a *documented* short slug and is not a *bounded* one —
     * nothing in that class, in this one or in the column trims it. The
     * consequence is not a truncated string in a log: a `22001` from this
     * insert escapes as a `QueryException` on the path that runs **after** a
     * tenant has already been erased and committed, where nothing catches it —
     * so the erasure would look unfinished for ever on the `28` §9.5 screen and
     * every later due deletion in the run would be skipped. 2045's defect,
     * reached through a vendor's error body.
     *
     * 255 rather than a rounder number because the column is what has to fit,
     * and Postgres counts `varchar` in characters — which is why the cut is
     * `mb_substr` and never `substr` (`L1Derivation`'s own note: halving a
     * multi-byte character produces bytes no encoding can read).
     */
    private const REASON_LIMIT = 255;

    public function __construct(
        private readonly ZernioGbpClient $zernio,
        private readonly AuditService $audit,
        private readonly ZernioSpend $spend,

        // ⛔ **THE SECOND LAYER, AND IT IS THE ONE THAT IS LIVE TODAY** (6455,
        // 6483). `GbpConnectionPolicy` asks the *role*, and inside an
        // impersonation session the role asked is the **owner's** — so the
        // policy answers yes to a support agent acting as them.
        // {@see ImpersonationCapability::ManageConnections} has said since it
        // was declared that a time-boxed session may not mint a credential that
        // outlives it, and until now it had no call site anywhere.
        private readonly Impersonation $impersonation,

        // ⚠️ **A BELL, AND THE ONLY ONE THIS CLASS RINGS** (R25, 6768).
        // `OperatorAlerts::raise()` returns rather than throws and contains
        // every failure inside itself, so nothing on these paths can be stopped,
        // delayed or degraded by the alert path — which is what lets it be
        // called from a webhook handler whose real work is unrelated to
        // alerting.
        private readonly OperatorAlerts $alerts,
    ) {}

    /**
     * This location's connection, if it has ever started one.
     */
    public function forLocation(Location $location): ?GbpConnection
    {
        return GbpConnection::query()
            ->where('location_id', $location->id)
            ->first();
    }

    /**
     * One connection of this tenant's, by id.
     *
     * ⚠️ **The screen resolves through here rather than through implicit route
     * binding or its own query**, on decision 1234's reasoning: a guard that
     * lives outside the code the screen is responsible for is a guard a
     * component test cannot falsify, because `Livewire::test()` runs no
     * middleware and no route binding at all (809). The global scope is what
     * refuses a foreign id, and this is where that refusal can be driven.
     *
     * @throws ModelNotFoundException<GbpConnection>
     */
    public function find(int $id): GbpConnection
    {
        return GbpConnection::query()->findOrFail($id);
    }

    /**
     * Every connection this tenant has, keyed by location id.
     *
     * One query for a screen listing locations. It exists so that the screen
     * does not grow a `hasOne` on `Location`: that would be a second reader of
     * `gbp_connections` and the lint holding this chokepoint would have to be
     * widened for a list — decision 624's shape, where two allowlist entries for
     * one feature each look reasonable on their own diff.
     *
     * @return Collection<int, GbpConnection>
     */
    public function forLocations(): Collection
    {
        return GbpConnection::query()->get()->keyBy('location_id');
    }

    /**
     * Location ids with a usable connection — the inverse of {@see forLocation()}.
     *
     * Exists so a scheduled fan-out does not become a second reader of
     * `gbp_connections` under its own query (the sole-writer lint would have to
     * grow an allowlist entry for a console command — decision 624's shape).
     *
     * @return Collection<int, int>
     */
    public function usableLocationIds(): Collection
    {
        return $this->usable()->orderBy('id')->pluck('location_id');
    }

    /**
     * Whether this tenant has any location `gbp:sync` will actually read.
     *
     * ⛔ **IT SHARES ITS PREDICATE WITH {@see self::usableLocationIds()} RATHER
     * THAN RESTATING IT, AND THAT IS THE WHOLE REASON THIS METHOD EXISTS
     * INSTEAD OF A `count()` AT THE CALL SITE** (9917). Its one caller is
     * `Livewire\Account\Home`, which uses the answer to decide whether the
     * owner is told *"connect Google and we will start counting these"* beneath
     * their Google review count. `SyncGoogleReviews` enumerates
     * `usableLocationIds()`, so a second, separately-written predicate here
     * would let the sentence and the sweep disagree about the same tenant —
     * the screen inviting somebody to connect an account we are already
     * reading, or falling silent about one we are not. **They are one query or
     * they are two answers**, which is `CLAUDE.md`'s own rule about a guard
     * holding a re-typed twin of the list it reads.
     *
     * ⚠️ **It answers about the tenant in context**, through `BelongsToTenant`'s
     * global scope and the RLS predicate beneath it — never about a business id
     * passed in, which is the shape that would make it a cross-tenant reader.
     */
    public function hasUsableConnection(): bool
    {
        return $this->usable()->exists();
    }

    /**
     * The one definition of *usable* — connected, with an account ref.
     *
     * ⚠️ It restates {@see GbpConnection::isUsable()} in SQL rather than calling
     * it, because that method answers about a hydrated row and this has to
     * answer about the table. The two are pinned together by
     * `Feature\Proof\ProofDefinitionsTest`, which drives one row through both.
     *
     * @return Builder<GbpConnection>
     */
    private function usable(): Builder
    {
        return GbpConnection::query()
            ->whereNotNull('account_ref')
            ->where('status', GbpConnectionStatus::Connected);
    }

    /**
     * Start a connection and return where to send the owner.
     *
     * ⚠️ **The row is written only after the provider answers**, so `pending`
     * means "this owner was sent to a consent screen" rather than "somebody
     * pressed a button once". A row written first would make a failed vendor
     * call indistinguishable from an abandoned flow, and the screen would offer
     * *Finish connecting* to somebody who never left.
     *
     * ⛔ **THE SPEND CHECK IS FIRST, AND BEFORE THE VENDOR CALL RATHER THAN
     * AFTER IT** (4720, 4721). Zernio bills per connected account per month, so
     * the money is committed the moment an account is connected — and the first
     * thing `profileRefFor()` does is create a profile at the vendor. Checking
     * after would leave a chargeable profile in their workspace for a connection
     * we then refused, and checking at {@see complete()} would refuse an owner
     * who had *already* granted `business.manage` on their Google listing at
     * Zernio's consent screen: the grant, and therefore the subprocessor
     * relationship, begins there rather than here.
     *
     * ⚠️ **A refusal here is not an error and the screen must not render it as
     * one** — see {@see GbpConnectionRefused::ceilingReached()}. It routes this
     * location onto rule 44's `handoff()` path, which is built and guaranteed.
     *
     * ⚠️ **THE CALLBACK URL IS MINTED HERE RATHER THAN PASSED IN, AND THAT IS A
     * NARROWING RATHER THAN A CONVENIENCE** (6602). It used to be a `string`
     * parameter, so every caller was trusted to hand over a *signed* URL and
     * nothing checked that they had; the one value the whole callback design
     * rests on was a caller's to get right. It cannot be a parameter any more in
     * any case, because {@see GbpConnectController::callbackUrlFor()} now signs
     * the profile reference into it and the profile is not knowable until
     * `profileRefFor()` has answered — three lines below where the caller would
     * have had to build it.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`.
     *
     * ⛔ **AND THE IMPERSONATION REFUSAL IS BEFORE EVEN THAT.** A refusal that
     * costs nothing and is about the actor rather than the account belongs above
     * one that reads a ledger, and this one has to sit at the service instead of
     * only at the screen for the reason every other capability does: a policy is
     * asked by one component, and a service is asked by everything that ever
     * reaches it.
     *
     * @throws GbpConnectionRefused
     * @throws GbpRequestFailed
     * @throws ImpersonationRefused when support attempts it from inside a
     *                              session
     */
    public function begin(Location $location, string $actor): string
    {
        $this->impersonation->refuse(ImpersonationCapability::ManageConnections);

        // Skipped for a location that already holds a usable account: it is
        // already on the meter, so reconnecting it adds nothing to the bill and
        // refusing it would strand an owner whose grant lapsed behind a ceiling
        // their own connection is already inside.
        $existing = $this->forLocation($location);

        if (($existing === null || ! $existing->isUsable()) && ! $this->spend->allowsNewAccount()) {
            throw GbpConnectionRefused::ceilingReached();
        }

        $business = Business::findOrFail(Tenancy::idOrFail());

        $profileRef = $this->profileRefFor($business);

        $url = $this->zernio->connectUrl(
            $profileRef,
            GbpConnectController::callbackUrlFor($location, $profileRef),
        );

        DB::transaction(function () use ($location, $business, $profileRef, $actor): void {
            // ⛔ **THE LAST MOMENT AT WHICH THIS MAPPING IS KNOWABLE AT ALL**
            // (6900). Everything that records a profile beside a business —
            // `gbp_connections` — is RLS-`FORCE`d, so from a platform sweep with
            // no tenant it reads zero rows (4732), and an owner who grants
            // access at Zernio and never returns leaves no completed row
            // anywhere. Recorded here, outside the boundary, or the question
            // *"whose abandoned flow are we paying for?"* is unaskable
            // afterwards — 4880's argument for `revocation_owed_at`, one step
            // earlier in the same flow.
            $this->recordProfile($profileRef, (int) $business->id);

            $connection = $this->forLocation($location);

            if ($connection === null) {
                $connection = new GbpConnection;
                $connection->location_id = $location->id;
            }

            $connection->provider = GbpProvider::Zernio;
            $connection->provider_profile_ref = $profileRef;

            // ⚠️ A reconnection does not clear the account ref it already has.
            // Until the owner comes back, the old binding is still the true
            // one — blanking it here would take a working connection offline
            // the moment somebody pressed the button and then changed their
            // mind, and the CHECK constraint forbids the pairing anyway.
            $connection->status = $connection->isUsable()
                ? $connection->status
                : GbpConnectionStatus::Pending;

            $connection->save();

            $this->audit->record('gbp.connect_started', $actor, $connection, [
                'location_id' => $location->id,
            ]);
        });

        return $url;
    }

    /**
     * Bind the account the provider handed back.
     *
     * ⛔ **READ THIS BEFORE SIMPLIFYING ANY OF THE THREE GUARDS BELOW — 6491 WAS
     * A CROSS-TENANT WRITE AND IT LIVED HERE FOR MONTHS BEHIND A DOCBLOCK THAT
     * SAID IT COULD NOT** (6600–6604). What this method used to hold was one
     * guard, `$profileRef !== null && $profileRef !== $connection->provider_profile_ref`,
     * and it failed on both halves of its own sentence:
     *
     *   - **A callback that OMITS `profileId` satisfied it vacuously**, so the
     *     profile was never compared with anything — 256's shape inside a
     *     security check, where a lint that matches nothing passes.
     *   - **A callback that SUPPLIES it satisfied it trivially**, because
     *     `profileId` is appended by the browser exactly like `accountId` and is
     *     this business's *own* reference: whoever holds a signed callback URL —
     *     an ordinary owner, legitimately — can send their own profile beside
     *     any account id they like. The guard compared two values from the same
     *     untrusted place and read as a tenant boundary.
     *
     * And the consequence was not a refusal but a **move**: {@see bindAccount()}
     * is an `updateOrCreate` keyed on the unique `account_ref`, so a foreign
     * account id rewrote `business_id` and `location_id` on the surviving
     * binding row. One request pointed this tenant's location at another
     * tenant's Google listing **and** repointed that tenant's binding — the same
     * row 5073 and 4880–4888 are about, reached from a third direction.
     *
     * ## What replaces it, in the order the guards run
     *
     *   `notStarted()`   there is a `pending` row for this location at all.
     *   `wrongProfile()` **twice, and the two are not redundant.** First the
     *                    *signed* expectation from the callback URL against the
     *                    stored `provider_profile_ref` — an expectation the
     *                    sender cannot alter without failing the signature, so
     *                    the comparison can no longer be skipped by leaving a
     *                    parameter out. Then the vendor's own `profileId`, when
     *                    it sent one, against that same expectation: still
     *                    advisory, still worth refusing on, and now incapable of
     *                    being the *only* thing consulted.
     *   `accountNotOnProfile()` ⛔ **THE ONLY ONE OF THE FOUR WHOSE OTHER SIDE IS
     *                    NOT SUPPLIED BY THE SENDER.** Zernio is asked whether
     *                    this account is on this business's profile.
     *
     * ⚠️ **THE PROFILE COMPARISONS ARE KEPT DELIBERATELY, THOUGH THE LAST GUARD
     * SUBSUMES THEM.** They cost nothing, they refuse a stale tab before we
     * spend a vendor round trip on it, and — the reason that matters — they are
     * the only thing standing if `gbp.zernio_enabled` is ever read differently
     * or the ownership call is one day made conditional. Deleting them would
     * leave a single point of failure on a cross-tenant write.
     *
     * ⚠️ **AND THE VENDOR CALL IS DELIBERATELY NOT INSIDE THE TRANSACTION.** It
     * is a network round trip; holding a row lock across one is how a vendor
     * timeout becomes a database incident.
     *
     * @param  string  $expectedProfileRef  From the signed callback URL, never
     *                                      from `profileId`. See the controller.
     * @param  ?string  $profileRef  The vendor's own `profileId`, if it sent one.
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`.
     *
     * @throws GbpConnectionRefused
     * @throws GbpRequestFailed when Zernio cannot be asked — which refuses the
     *                          connect rather than binding an unchecked account
     */
    public function complete(
        Location $location,
        string $accountRef,
        string $expectedProfileRef,
        ?string $profileRef,
        ?string $label,
        string $actor,
    ): GbpConnection {
        $connection = $this->forLocation($location);

        if ($connection === null) {
            throw GbpConnectionRefused::notStarted();
        }

        // ⚠️ Fails closed on a null stored ref as well as on a mismatch: a
        // connection row with no profile is one `begin()` never wrote, and
        // `$expectedProfileRef` is a non-empty string on every real callback.
        if ($expectedProfileRef === '' || $expectedProfileRef !== $connection->provider_profile_ref) {
            throw GbpConnectionRefused::wrongProfile();
        }

        if ($profileRef !== null && $profileRef !== $expectedProfileRef) {
            throw GbpConnectionRefused::wrongProfile();
        }

        if (! $this->zernio->profileOwnsAccount($expectedProfileRef, $accountRef)) {
            throw GbpConnectionRefused::accountNotOnProfile();
        }

        $connection = DB::transaction(function () use ($connection, $accountRef, $label, $actor): GbpConnection {
            // Assigned rather than filled: `account_ref` is guarded on the model
            // precisely so the value deciding whose reviews we read can never
            // arrive through mass assignment from a request.
            $connection->account_ref = $accountRef;
            $connection->external_label = $label;
            $connection->status = GbpConnectionStatus::Connected;
            $connection->connected_at = now();
            $connection->disconnected_at = null;
            $connection->last_error = null;
            $connection->last_checked_at = now();

            $connection->save();

            $this->bindAccount($accountRef, (int) $connection->business_id, (int) $connection->location_id, $actor);

            $this->audit->record('gbp.connected', $actor, $connection, [
                'location_id' => $connection->location_id,
                'provider' => $connection->provider->value,
                'account_ref' => $accountRef,
            ]);

            return $connection;
        });

        // First activation backfill — GBP-02. Outside the transaction so a
        // sync-queue worker never reads the row before it commits.
        //
        // ⚠️ **THE JOB REFUSES ITSELF WHEN `gbp.zernio_enabled` IS OFF, AND THE
        // REFUSAL IS IN {@see SyncGoogleReviewsJob::canExecute()} RATHER THAN
        // IN ITS `execute()`** (9918). Said bare, that sentence sent a scout to
        // `execute()`, which reads no registry key at all, and it was reported
        // as a protection claimed and not built. It is built:
        // `AutopilotJob::handle()` calls `canExecute()` and routes a false to
        // `handoff()`, so the vendor is never reached. **A prose claim about a
        // gate now names the method that holds it**, and
        // `Feature\Proof\ProofDefinitionsTest` drives the refusal rather than
        // leaving this comment as the evidence for it.
        SyncGoogleReviewsJob::dispatch(
            (int) $connection->business_id,
            (int) $connection->location_id,
        );

        return $connection;
    }

    /**
     * Advance or finish a sync cursor on this connection.
     *
     * ⚠️ The only writer of `sync_cursor` / `last_synced_at` (decision 1348).
     */
    public function advanceSync(GbpConnection $connection, ?string $cursor, bool $finished): GbpConnection
    {
        $connection->sync_cursor = $finished ? null : $cursor;

        if ($finished) {
            $connection->last_synced_at = now();
        }

        $connection->save();

        return $connection;
    }

    /**
     * Resolve a platform binding for a Zernio account id (webhook path).
     */
    public function bindingForAccount(string $accountRef): ?GbpAccountBinding
    {
        return GbpAccountBinding::query()
            ->where('account_ref', $accountRef)
            ->first();
    }

    /**
     * Write down that the provider corroborated — or contradicted — a binding.
     *
     * ⛔ **THIS IS THE WHOLE OF WHAT THE `account.connected` WEBHOOK IS
     * PERMITTED TO DO, AND THE LIMIT IS THE VENDOR'S RATHER THAN OURS** (6615,
     * 6762–6764). The webhook is strictly stronger than the redirect on one
     * question and strictly **weaker** on another, and building it as a
     * replacement would have traded the second away:
     *
     *   stronger  *"is this account really on this profile?"* — it is signed,
     *             server-to-server, and the browser is not in the path at all.
     *   weaker    ⛔ ***"which of this business's locations did the owner
     *             mean?"* — the payload cannot say, and no vendor mechanism
     *             exists to make it say.** `GET /v1/connect/{platform}` takes
     *             `profileId`, `redirect_url`, `headless` and `loginMethod` and
     *             **no state passthrough of ours** (read 2026-08-21); one
     *             profile serves a whole business (see {@see profileRefFor()},
     *             which reuses the first stored ref for every location), so a
     *             `profileId` names a *tenant* and never a location. The signed
     *             `location` in our own callback URL is the only carrier of
     *             that fact anywhere in the flow.
     *
     * So the redirect stays the thing that completes a connection and this
     * records what the vendor independently says about the result.
     *
     * @param  string  $profileRef  The payload's `account.profileId`, which is
     *                              **compared and never used to select a
     *                              tenant** — the caller resolved the tenancy
     *                              from our own binding index before this runs.
     * @return 'confirmed'|'mismatched'
     */
    public function recordProviderConfirmation(GbpConnection $connection, string $accountRef, string $profileRef): string
    {
        if ($connection->provider_profile_ref !== $profileRef) {
            $this->audit->record(self::BINDING_MISMATCHED, self::WEBHOOK_ACTOR, $connection, [
                'location_id' => $connection->location_id,
                'account_ref' => $accountRef,
                'expected_profile_ref' => $connection->provider_profile_ref,
                'provider_profile_ref' => $profileRef,
            ]);

            $this->ringMismatch($connection);

            return 'mismatched';
        }

        $this->audit->record(self::BINDING_CONFIRMED, self::WEBHOOK_ACTOR, $connection, [
            'location_id' => $connection->location_id,
            'account_ref' => $accountRef,
            'provider_profile_ref' => $profileRef,
        ]);

        return 'confirmed';
    }

    /**
     * Tell the operator that the vendor contradicts a binding we hold.
     *
     * ⛔ **THE AUDIT ROW IS THE EVIDENCE AND THIS IS ONLY THE BELL** (6768).
     * Everything that identifies the connection at the vendor — the account
     * reference and both profile references — stays on the `audit_log` row
     * written one line above, inside the tenant's own record. `operator_alerts`
     * is platform-scoped, carries no tenancy and no row-level security, and its
     * `summary` becomes the body of a **text message**; 4884's rule about a
     * reference on an SMS being an invitation to paste it into a vendor console
     * applies to every one of those values, and the account reference is the one
     * this class's own docblock says never leaves it.
     *
     * ⚠️ **SO THE ALERT CARRIES TWO NUMBERS AND A SENTENCE**, which is what an
     * operator needs to find the record: which customer, which location. The
     * subject is the business id, which is also the de-duplication key — see
     * {@see OperatorAlertKind::GbpBindingMismatched} for why that is not the
     * location and not the provider.
     *
     * ⚠️ **THE SENTENCE IS KEPT WELL INSIDE THE 300-CHARACTER CLAMP AND THAT IS
     * NOT HOUSEKEEPING.** `OperatorAlerts::fire()` cuts a summary rather than
     * refusing it — correctly, since a long line is no reason to swallow an
     * alert — so a later edit adding a clause would lose the end of this one
     * **silently**, and the end of this one is where it says nothing was changed
     * and where the evidence is. A test asserts the last words survive.
     *
     * ⚠️ **AND IT RINGS AND DOES NOTHING ELSE** (6767). No re-point, no rebind,
     * no disconnect, no flag on the connection row. `Architecture\GbpTest`
     * already refuses those verbs in the reconciliation; here the refusal is the
     * design, and the alert exists so that a person makes the decision instead
     * of a webhook making it at four in the morning.
     */
    private function ringMismatch(GbpConnection $connection): void
    {
        $this->alerts->raise(
            OperatorAlertKind::GbpBindingMismatched,
            (string) $connection->business_id,
            'The provider reports a connected Google account on a different profile '
            .'than the one recorded for the business holding it, so our record of which '
            .'customer owns that listing disagrees with theirs. Nothing has been changed; '
            .'the references are in that business\'s audit log.',
            [
                'business_id' => $connection->business_id,
                'location_id' => $connection->location_id,
                'provider' => $connection->provider->value,
            ],
        );
    }

    /**
     * Mirror a provider-side disconnect without calling the vendor.
     *
     * Used by the account.disconnected webhook: Zernio already revoked the
     * grant, so {@see disconnect()} would fail against an account that is gone.
     */
    public function recordExternalDisconnect(GbpConnection $connection, string $actor): GbpConnection
    {
        return $this->markDisconnected($connection, $actor, 'provider_webhook_disconnected');
    }

    /**
     * Ask the provider whether this connection still works.
     *
     * A dead connection is written down; anything else — our key, a quota wall,
     * their outage — propagates, on {@see ZernioGbpClient::connectionHealthy()}'s
     * own reasoning. Reporting "the tenant disconnected" when the vendor is down
     * sends an owner to re-authorise something that was never broken.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`, `system`.
     *
     * @throws GbpRequestFailed
     */
    public function refreshHealth(GbpConnection $connection, string $actor): GbpConnection
    {
        if (! $connection->isUsable()) {
            return $connection;
        }

        $accountRef = $connection->account_ref;

        // Narrowing for the analyser, and true by `isUsable()` above.
        if ($accountRef === null) {
            return $connection;
        }

        $healthy = $this->clientFor($connection)->connectionHealthy($accountRef);

        if ($healthy) {
            $connection->last_checked_at = now();
            $connection->last_error = null;
            $connection->save();

            return $connection;
        }

        return $this->markDisconnected($connection, $actor, 'provider_reports_unhealthy');
    }

    /**
     * End the connection, at the provider first and here second.
     *
     * ⚠️ **The order is the point.** Our row stops us reading; only the provider
     * call ends the grant. If the vendor call fails, nothing is written — an
     * owner told "Disconnected" while Zernio still holds `business.manage` on
     * their listing has been told something untrue about who can reach their
     * Google profile.
     *
     * ⛔ **REFUSED INSIDE AN IMPERSONATION SESSION, LIKE `begin()`** (6483).
     * `ImpersonationCapability::ManageConnections` names *"connecting or
     * disconnecting"* in one breath, and the destructive half is the one an
     * agent is likelier to reach for on a call. It is deliberately **not** on
     * {@see refreshHealth()}, which asks a question and records the answer.
     *
     * ⚠️ **`revokeOwedGrants()` DOES NOT COME THROUGH HERE AND MUST NOT START
     * TO.** It calls the client directly, after an erasure has committed, with
     * no session and no user — see 4880. A refusal on that path would leave a
     * deleted customer's grant live.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`.
     *
     * @throws GbpRequestFailed
     * @throws ImpersonationRefused when support attempts it from inside a
     *                              session
     */
    public function disconnect(GbpConnection $connection, string $actor): GbpConnection
    {
        $this->impersonation->refuse(ImpersonationCapability::ManageConnections);

        $accountRef = $connection->account_ref;

        if ($accountRef !== null) {
            $this->zernio->disconnectAccount($accountRef);
        }

        return $this->markDisconnected($connection, $actor, 'owner_disconnected');
    }

    /**
     * Record that this tenant's grants are owed a revocation, before it is gone.
     *
     * ⛔ **CALLED FROM INSIDE THE ERASURE TRANSACTION, AND THAT IS THE WHOLE
     * ORDERING ARGUMENT** (4880, 4881). `gbp_connections` is destroyed by
     * `$business->delete()`; `gbp_account_bindings` is not, because it has no
     * foreign key (4730). So this is the last moment at which the platform can
     * learn which Zernio accounts belonged to the tenant it is about to destroy —
     * afterwards the question is unaskable, since every table that could answer
     * it is RLS-`FORCE`d and reads zero rows from a sweep with no tenant (4732).
     *
     * ⚠️ **IT STAMPS RATHER THAN CALLS.** The vendor call happens after the
     * transaction commits, never inside it: a third-party HTTP call under an open
     * transaction holds row locks across somebody else's outage, and — worse — a
     * revocation made before a rollback would strip a **surviving** tenant's
     * Google connection with nothing left to say why. Stamping first also makes
     * the crash case safe: a process that dies between the commit and the vendor
     * call leaves the obligation on disk for `gbp:revoke-owed-grants` to find.
     *
     * @return int how many grants are now owed for this tenant
     */
    public function markGrantsOwedForDeletion(): int
    {
        // ⛔ **ZERNIO ONLY, AND THE FILTER IS NOT TIDINESS** (4888d).
        // `revokeOwedGrants()` calls the Zernio client directly rather than
        // through {@see clientFor()}, because the connection row it would need is
        // destroyed by the erasure — so a `Direct` connection stamped here would
        // send *that* provider's account reference to Zernio's `DELETE
        // /v1/accounts/{id}`. `clientFor()` refuses `Direct` outright today and
        // nothing in `app/` creates one, which is exactly why this filter has to
        // exist before one does.
        $refs = GbpConnection::query()
            ->whereNotNull('account_ref')
            ->where('provider', GbpProvider::Zernio)
            ->where('status', GbpConnectionStatus::Connected)
            ->orderBy('id')
            ->pluck('account_ref')
            ->all();

        if ($refs === []) {
            return 0;
        }

        GbpAccountBinding::query()
            ->whereIn('account_ref', $refs)
            ->whereNull('revocation_owed_at')
            ->update(['revocation_owed_at' => now()]);

        // Counted back rather than taken from the update's own return, which
        // reports rows *changed* — a binding already stamped by a previous
        // attempt is still a grant we owe, and would have gone uncounted.
        return GbpAccountBinding::query()
            ->whereIn('account_ref', $refs)
            ->whereNotNull('revocation_owed_at')
            ->count();
    }

    /**
     * End the grants of tenants that no longer exist.
     *
     * ⚠️ **NO TENANT, NO AUDIT ENTRY, AND BOTH ARE DELIBERATE.** The business is
     * gone, so `audit_log` — tenant-owned, `business_id` NOT NULL, cascading —
     * has nowhere to put this (489's wall, as `TenantDeletion` itself records).
     * What stands in for it is the vendor log, which `ZernioGbpClient` writes for
     * every call, and the disappearance of the binding row itself.
     *
     * ⚠️ **THE BINDING IS DELETED ONLY ON SUCCESS**, on {@see disconnect()}'s
     * order-is-the-point rule: our row stops us reading, and only the vendor call
     * ends `business.manage` on the listing. A row deleted after a failed call
     * would destroy the last evidence that a third party still holds write access
     * to a former customer's Google profile — the one record 4730 kept the
     * foreign key off this table to preserve.
     *
     * ⚠️ **AND DELETING IT IS WHAT STOPS THE BILL.** `zernio:meter` counts
     * bindings (4721), so an account that is genuinely disconnected must leave
     * the table or we keep accruing a cost the vendor is no longer charging.
     *
     * ⛔ **AND A STAMPED ROW WHOSE BUSINESS IS STILL ALIVE IS SKIPPED RATHER
     * THAN REVOKED** (5074). Nothing clears `revocation_owed_at` except deleting
     * the row on success, so a stamp outlives the tenancy it was written for —
     * and {@see bindAccount()} used to rewrite `business_id` on a re-connect
     * while leaving the stamp set, which pointed a *live* customer's Google
     * account at this sweep. 5073 closes that at the writer; this closes it at
     * the reader, because rows stamped before that fix shipped are already on
     * disk and tonight's sweep is what would act on them. The probe is the same
     * one {@see bindingsWithNoSurvivingBusiness()} uses, run over the owed set
     * rather than the whole index, so it is bounded by the number of
     * obligations outstanding — which in the steady state is none.
     *
     * ⚠️ **SKIPPED IS ITS OWN COUNT AND NOT AN `outstanding`.** Outstanding
     * means the vendor was asked and refused; skipped means it was never asked,
     * and telling an operator that Zernio *"still holds read and write access to
     * a former customer's listing"* about a business that is still a customer
     * sends them to disconnect a live account by hand.
     *
     * @param  ?int  $businessRef  narrow to one former tenant, or null for all
     * @return array{revoked: int, outstanding: int, skipped: int}
     */
    public function revokeOwedGrants(?int $businessRef = null): array
    {
        $query = GbpAccountBinding::query()
            ->whereNotNull('revocation_owed_at')
            ->orderBy('id');

        if ($businessRef !== null) {
            $query->where('business_id', $businessRef);
        }

        $revoked = 0;
        $outstanding = 0;
        $skipped = 0;

        foreach ($query->get() as $binding) {
            if ($this->businessSurvives((int) $binding->business_id)) {
                $skipped++;

                continue;
            }

            if ($this->attemptRevocation($binding, self::SYSTEM_ACTOR)) {
                $revoked++;
            } else {
                $outstanding++;
            }
        }

        return ['revoked' => $revoked, 'outstanding' => $outstanding, 'skipped' => $skipped];
    }

    /**
     * Retry exactly one owed grant, from an operator's own click.
     *
     * ⚠️ **PER-ROW ON PURPOSE.** `revokeOwedGrants($businessRef)` acts on
     * every owed binding a business has, which is right for the sweep and
     * wrong for a screen: a business with two owed locations must let an
     * operator retry one without touching the other. This targets a binding
     * id rather than a business id for exactly that reason.
     *
     * ⛔ **AND IT REFUSES A ROW WHOSE BUSINESS IS STILL ALIVE, AT THE SERVICE
     * AND NOT ONLY ON THE SCREEN** (5074). The screen withholds the button on
     * exactly that row, which is a rendering decision and therefore not a
     * refusal — the Livewire method is callable with any binding id by anybody
     * who can reach the component. The consequence of getting it wrong is the
     * one 4884 refuses to allow a sweep: a paying customer's Google listing
     * disconnected, with no way back but asking the owner to consent again at
     * Zernio.
     *
     * @throws InvalidArgumentException if this binding is not currently owed a
     *                                  revocation — already gone, already
     *                                  revoked, or never owed at all — or if the
     *                                  business it names still exists
     */
    public function retryRevocation(int $bindingId, string $actor): bool
    {
        $binding = GbpAccountBinding::query()
            ->whereNotNull('revocation_owed_at')
            ->whereKey($bindingId)
            ->first();

        if ($binding === null) {
            throw new InvalidArgumentException(
                'This grant is not currently recorded as owed a revocation.'
            );
        }

        if ($this->businessSurvives((int) $binding->business_id)) {
            throw new InvalidArgumentException(
                'This grant names a business that still exists, so revoking it would '
                .'disconnect a live customer\'s Google listing. The obligation is stale '
                .'and nothing was sent to Zernio.'
            );
        }

        return $this->attemptRevocation($binding, $actor);
    }

    /**
     * How many grants are waiting to be revoked, across the platform.
     */
    public function owedGrantCount(): int
    {
        return GbpAccountBinding::query()->whereNotNull('revocation_owed_at')->count();
    }

    /**
     * Every grant still owed a revocation, oldest first, with what has been
     * tried against it — the Ops screen's one read (4888(a)).
     *
     * ⚠️ **ONE ATTEMPT QUERY PER BINDING, DELIBERATELY.** `gbp_account_
     * bindings` has no foreign key onto this log (see the migration), so there
     * is no relationship Eloquent can eager-load, and the steady state this
     * whole obligation is built to reach is an empty result — a join buys
     * nothing worth the row it would save.
     *
     * ⛔ **THE HISTORY IS CORRELATED ON THE WHOLE TRIPLE, NOT ON `account_ref`
     * ALONE** (5075). One Zernio account can be bound to more than one business
     * over its life — an owner who resubscribes, a franchise, a sold location —
     * and 5062 copied `business_id` and `location_id` onto every attempt row for
     * precisely this. Matching on the account alone renders one tenancy's
     * failure history under another tenancy's row, which on this screen is a
     * count of failures attributed to the wrong former customer.
     *
     * ⚠️ **`businessSurvives` IS ON THE ROW BECAUSE A STAMP CAN BE STALE**
     * (5074). It is the one thing an operator cannot see from the numbers and
     * cannot recover from if they get it wrong. The probe costs one tenancy
     * switch per **owed** row — the set this screen exists to empty — and not
     * one per binding on the platform, which is the cost 4887 declined and
     * {@see bindingsWithNoSurvivingBusiness()} still carries.
     *
     * @return Collection<int, array{binding: GbpAccountBinding, failedAttempts: int<0, max>, lastAttempt: ?GbpGrantRevocationAttempt, businessSurvives: bool}>
     */
    public function owedGrants(): Collection
    {
        return GbpAccountBinding::query()
            ->whereNotNull('revocation_owed_at')
            ->orderBy('revocation_owed_at')
            ->get()
            ->map(fn (GbpAccountBinding $binding): array => [
                'binding' => $binding,
                'failedAttempts' => $this->attemptsFor($binding)
                    ->where('outcome', GbpRevocationOutcome::Failed)
                    ->count(),
                'lastAttempt' => $this->attemptsFor($binding)
                    ->latest('id')
                    ->first(),
                'businessSurvives' => $this->businessSurvives((int) $binding->business_id),
            ]);
    }

    /**
     * Every recorded attempt against this binding — account, business and
     * location, all three.
     *
     * @return Builder<GbpGrantRevocationAttempt>
     */
    private function attemptsFor(GbpAccountBinding $binding): Builder
    {
        return GbpGrantRevocationAttempt::query()
            ->where('account_ref', $binding->account_ref)
            ->where('business_id', $binding->business_id)
            ->where('location_id', $binding->location_id);
    }

    /**
     * Try to end one binding's grant, and log what happened whichever way it
     * goes.
     *
     * ⚠️ **THE ONE PLACE `GbpGrantRevocationAttempt` IS WRITTEN.** Both public
     * callers above funnel through here, so the sweep, the immediate
     * post-erasure call and an operator's manual retry all leave the same
     * shape of evidence behind them.
     *
     * ⚠️ **THE OUTCOME NAMES WHAT ZERNIO ANSWERED, NOT WHAT IS NOW TRUE**
     * (4888(c)). A 200 to their `DELETE` is not independently verified against
     * the account at Google — see {@see GbpRevocationOutcome::Revoked}'s own
     * docblock.
     */
    private function attemptRevocation(GbpAccountBinding $binding, string $actor): bool
    {
        try {
            $this->zernio->disconnectAccount($binding->account_ref);
        } catch (GbpRequestFailed $e) {
            // Their outage, our key, or the integration switched off. The
            // stamp stays and the sweep tries again tomorrow; nothing here
            // reports success it did not have.
            $this->recordAttempt($binding, GbpRevocationOutcome::Failed, $e->reason, $actor);

            return false;
        }

        $this->recordAttempt($binding, GbpRevocationOutcome::Revoked, null, $actor);

        $binding->delete();

        return true;
    }

    /**
     * Write one row of evidence, and contain a failure to write it.
     *
     * ⛔ **THE NET CONTAINS THE STATEMENT, NOT A TRANSACTION — AND THIS
     * DOCBLOCK SAID OTHERWISE UNTIL 5101.** It read *"a failure to write the log
     * must not escape"*, unconditionally. What the `catch` actually stops is the
     * exception from **this statement**. If this ran inside an ambient
     * transaction, a failed `INSERT` would put PostgreSQL's transaction into the
     * aborted state and defer the failure to the *next* statement — on the
     * success path that is `$binding->delete()` in
     * {@see self::attemptRevocation()}, which would throw `25P02` into
     * `ExecuteTenantDeletions::handle()` and reproduce 2045 **with the net in
     * place**. A guard described as covering more than it covers is what stops
     * the next reviewer looking (314–316).
     *
     * ⛔ **SO THIS PATH MUST NEVER RUN INSIDE A TRANSACTION**, and today no
     * caller opens one: the nightly sweep and the operator's retry are both top
     * of their own request, and {@see TenantDeletion::revokeVendorGrants()} runs
     * **after** the erasure transaction has committed, which its own docblock
     * argues at length. That is pinned by a lint over the whole caller chain in
     * `tests/Feature/Architecture/GbpTest.php` rather than by this paragraph —
     * ⚠️ **and not by a runtime `DB::transactionLevel()` assertion, which cannot
     * be made honestly here**: `RefreshesTenantDatabase` wraps every test in a
     * transaction on this very connection, so the level is never zero under the
     * suite, and a guard exempted in tests is a guard nothing drives.
     *
     * ⚠️ **WHY THE NET IS WORTH HAVING ANYWAY** (5072).
     * {@see TenantDeletion::revokeVendorGrants()}'s rule is *"a failure does not
     * undo the erasure and does not pretend to succeed"*. An exception here does
     * neither — the account is already gone, and it escapes
     * `ExecuteTenantDeletions::handle()`, which has no catch: the §9.5 queue item
     * is never closed (2045's exact defect, a completed erasure looking
     * unfinished for ever on a screen) and every later due deletion in that run
     * is skipped. **The evidence row is worth a great deal and it is not worth
     * that.**
     *
     * ⚠️ **AND IT STAYS WHOLE RATHER THAN BEING SCOPED TO THE ERASURE CALLER**
     * (5102). The reasonable-looking alternative is to let
     * {@see self::retryRevocation()} surface its own log failure, since an
     * operator's click has no statutory run behind it. It is refused because of
     * *where* the throw would land: between a **successful** vendor call and the
     * `$binding->delete()` on the next line. The grant is already ended at
     * Zernio, and the binding would survive still stamped as owed — so tonight's
     * sweep sends a second `DELETE` for an account the vendor no longer knows,
     * and an operator shown an error retries a revocation that succeeded. A lost
     * evidence row is reported; a permanent false obligation is not.
     *
     * ⚠️ **THE VENDOR'S STRING IS CUT BEFORE IT IS THE DATABASE'S PROBLEM**
     * ({@see self::REASON_LIMIT}), so the commonest way this could have thrown —
     * a `22001` from an over-long `code` — no longer arises. The catch is the
     * outermost net for the failure nobody predicted, on
     * `OperatorAlerts::raise()`'s precedent and for its reason.
     *
     * ⚠️ **THE SUCCESS PATH STILL DELETES THE BINDING AFTERWARDS**, and that is
     * deliberate rather than overlooked: Zernio has already ended the grant, and
     * keeping the row would go on billing us for the account (4721) while the
     * sweep chased something that is gone. What is lost when this net catches
     * anything is the evidence row, which is why the loss is reported rather
     * than swallowed silently.
     *
     * ⛔ **AND THE FALLBACK CARRIES THE ACTOR, BECAUSE THE ACTOR IS THE WHOLE
     * POINT OF THE ROW THAT WAS LOST** (5102). The append-only guards on this
     * table were argued on the grounds that it is *the only record of which
     * operator pressed the button*; a fallback that logged the business, the
     * location and the outcome and **not** the actor could not rebuild the one
     * fact the table exists for. It is `audit_log`'s own vocabulary — `system`,
     * or `user:<id>` — and carries nothing the line did not already hold.
     *
     * ⚠️ **THE LOG LINE CARRIES NO VENDOR ERROR STRING AND NO PERSONAL DATA** —
     * the exception's *class*, `OperatorAlerts`' shape exactly. The account
     * reference is deliberately **not** on it either: it is the value that
     * decides whose Google listing a call reaches, and this class is where it
     * stays (see this class's own docblock, and
     * {@see self::bindingsWithNoSurvivingBusiness()} handing back refs for the
     * same reason). The business is destroyed; there is no name left anywhere on
     * this path.
     */
    private function recordAttempt(
        GbpAccountBinding $binding,
        GbpRevocationOutcome $outcome,
        ?string $reason,
        string $actor,
    ): void {
        try {
            GbpGrantRevocationAttempt::create([
                'account_ref' => $binding->account_ref,
                'business_id' => $binding->business_id,
                'location_id' => $binding->location_id,
                'outcome' => $outcome,
                'reason' => $reason === null ? null : mb_substr($reason, 0, self::REASON_LIMIT),
                'actor' => $actor,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('grant revocation attempt could not be recorded', [
                'business_id' => $binding->business_id,
                'location_id' => $binding->location_id,
                'outcome' => $outcome->value,
                // The one field the lost row existed to hold — see the docblock.
                'actor' => $actor,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Bindings whose business no longer exists and which nothing recorded.
     *
     * ⛔ **4732 SAID THIS QUESTION COULD NOT BE ASKED, AND AS A SET QUERY IT
     * STILL CANNOT BE** — `businesses` is RLS-`ENABLE`+`FORCE`d, so a join or a
     * `whereNotExists` from a sweep with no tenant returns zero rows and answers
     * *"every binding is an orphan"*. What works is asking **one binding at a
     * time, from inside the tenancy it names**: `Tenancy::actingAs()` sets
     * `app.business_id` to that id, and `businesses`' `tenant_isolation` policy
     * then admits exactly the row being asked about, if it is still there. A
     * deleted business answers false; a live one answers true. That is the same
     * mechanism {@see TenantDeletion::execute()} already
     * relies on to read the business it is about to destroy.
     *
     * ⚠️ **IT IS A REPORT AND NOTHING ACTS ON IT** (4884). Everything deleted
     * from now on is *recorded* by `markGrantsOwedForDeletion()`; this exists for
     * the accounts erased before that existed, and for those the orphan is
     * **inferred**. Revoking on an inference, from a nightly sweep, is how a
     * paying customer's Google listing gets disconnected by a bug in a filter —
     * 398's shape, where an outer condition supplies a confident wrong answer. So
     * a human reads the count and decides.
     *
     * ⚠️ It returns **business references and not bindings**, one per orphaned
     * row, so that its caller never holds a Zernio account id. The ref is the
     * label an operator can act on; the account id is the value that decides
     * whose Google listing a call reaches, and this class is where it stays.
     *
     * @return Collection<int, int>
     */
    public function bindingsWithNoSurvivingBusiness(): Collection
    {
        return GbpAccountBinding::query()
            ->whereNull('revocation_owed_at')
            ->orderBy('id')
            ->pluck('business_id')
            ->map(fn (mixed $businessId): int => (int) $businessId)
            ->reject(fn (int $businessId): bool => $this->businessSurvives($businessId))
            ->values();
    }

    /**
     * Does this business still exist? Asked from inside its own tenancy.
     */
    private function businessSurvives(int $businessId): bool
    {
        return (bool) Tenancy::actingAs(
            $businessId,
            static fn (): bool => Business::query()->whereKey($businessId)->exists(),
        );
    }

    /**
     * The client this connection is served by.
     *
     * Resolved from the stored provider on every call, never from a container
     * binding — decision 547 keeps both providers live permanently, so the two
     * cohorts exist at the same time and a binding chosen once cannot express
     * that.
     *
     * @throws GbpConnectionRefused
     */
    public function clientFor(GbpConnection $connection): GbpClient
    {
        return match ($connection->provider) {
            GbpProvider::Zernio => $this->zernio,

            // ⚠️ Refused rather than quietly served by Zernio. A location moved
            // to direct access has been told it no longer reads through a
            // subprocessor; falling back would make that untrue and nothing
            // would show it.
            GbpProvider::Direct => throw GbpConnectionRefused::providerUnavailable(),
        };
    }

    /**
     * Every account connected under our Zernio platform key, from the vendor.
     *
     * ⛔ **THIS METHOD EXISTS SO THAT {@see ZernioReconciliation} NEVER NAMES A
     * CLIENT** (6904). `GbpTest` holds a lint — *"nothing reaches a Google
     * Business client except through the connection store"* — whose reasoning
     * is that holding this store to one writer does nothing if something else
     * can resolve a client and call it with an account reference it got from
     * somewhere else. This call takes **no arguments at all**, so it cannot
     * carry that value; the allowlist does not grow anyway, because 624's shape
     * is a lint that gains one reasonable entry per feature until it holds
     * nothing. ⚠️ **THIS SAID "STAYS FOUR ENTRIES LONG" AND THE COUNT WAS THE
     * WRONG INSTRUMENT** (9089): the list has since gone down to three, because
     * one of the four named a file that reached a client only in a docblock,
     * and a length nobody could check was what made that invisible. **The rule
     * is that the list does not grow; its length is the lint's to state.**
     *
     * ⚠️ **IT DELIBERATELY DOES NOT WRITE ANYTHING.** The tempting next line —
     * *"we noticed a connected account with no binding, let us create one"* — is
     * the write `GbpTest`'s sibling lint on the meter already forbids, and 6764
     * is why it is wrong rather than merely out of scope: the vendor's list
     * cannot name a **location**, and a binding without one is a guess about
     * whose listing this is.
     *
     * @throws GbpRequestFailed
     */
    public function connectedAccountsAtProvider(): ZernioAccountList
    {
        return $this->zernio->connectedAccounts();
    }

    /**
     * Every platform binding, keyed by the account reference Zernio bills for.
     *
     * ⚠️ **A READER FOR {@see ZernioReconciliation}, AND IT LIVES HERE SO THAT
     * THE RECONCILIATION NEVER NAMES `GbpAccountBinding` AT ALL.** `GbpTest`
     * holds a two-part lint on that model — one writer, and a paired test
     * forbidding the one permitted *reader* from writing — and 624's shape is
     * two allowlist entries for one feature, each reasonable on its own diff.
     * A method here costs nothing and keeps the allowlist the length it is.
     *
     * @return Collection<string, GbpAccountBinding>
     */
    public function bindingsByAccountRef(): Collection
    {
        return GbpAccountBinding::query()->orderBy('id')->get()->keyBy('account_ref');
    }

    /**
     * The business behind each of these Zernio profiles, where we recorded one.
     *
     * ⛔ **ATTRIBUTION FOR A REPORT, AND NEVER ENOUGH TO BIND AN ACCOUNT**
     * (6764, 6779(a), 6901). A profile names a business; a binding needs a
     * location, and a business connecting three locations has three `pending`
     * rows against one profile. A caller reaching here to decide where a Google
     * account belongs is reading a fact that cannot answer its question.
     *
     * ⚠️ **A MISSING KEY MEANS "WE DID NOT RECORD IT", NEVER "IT IS NOBODY'S".**
     * Every profile made before {@see begin()} started writing this index is
     * absent, and so is any profile created directly in Zernio's own console.
     * The caller reports those as unattributable rather than guessing.
     *
     * @param  list<string>  $profileRefs
     * @return array<string, int>
     */
    public function businessesForProfiles(array $profileRefs): array
    {
        if ($profileRefs === []) {
            return [];
        }

        /** @var array<string, int> $map */
        $map = GbpProfileBinding::query()
            ->whereIn('profile_ref', $profileRefs)
            ->pluck('business_id', 'profile_ref')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $map;
    }

    /**
     * Record that this Zernio profile belongs to this business.
     *
     * ⛔ **`firstOrCreate` AND NOT `updateOrCreate`, AND THE DIFFERENCE IS THE
     * SAFETY PROPERTY** (6902). {@see bindAccount()} deliberately *moves* a
     * binding when a Google account changes hands, because 5073 argues that
     * move is correct. A profile cannot change hands: its name is derived from
     * the business id ({@see profileNameFor()}) and the vendor's `name` filter
     * is exact-match, so two businesses cannot converge on one. A second
     * business appearing against a recorded profile is therefore an anomaly,
     * and overwriting on it would silently re-attribute an orphan — sending an
     * operator to look for a stranger's connection inside a customer's account,
     * which is the false positive this whole surface is built to avoid.
     * **The first recorded attribution stands.**
     */
    private function recordProfile(string $profileRef, int $businessId): void
    {
        if ($profileRef === '') {
            return;
        }

        GbpProfileBinding::query()->firstOrCreate(
            ['profile_ref' => $profileRef],
            ['business_id' => $businessId],
        );
    }

    /**
     * The Zernio profile for this business, made if it is not there yet.
     *
     * Three sources in order, and the order is what makes it idempotent under a
     * timeout: a ref we already stored, then the vendor's own exact-name lookup,
     * then a create carrying an `Idempotency-Key`. Their documentation names the
     * middle step as the recovery for *"an ambiguous create (timeout followed by
     * a 409 on retry)"*.
     *
     * @throws GbpRequestFailed
     */
    public function zernioProfileForCurrentBusiness(): string
    {
        $business = Business::findOrFail(Tenancy::idOrFail());
        $profileRef = $this->profileRefFor($business);
        $this->recordProfile($profileRef, (int) $business->id);

        return $profileRef;
    }

    private function profileRefFor(Business $business): string
    {
        $stored = GbpConnection::query()
            ->whereNotNull('provider_profile_ref')
            ->where('provider', GbpProvider::Zernio)
            ->orderBy('id')
            ->value('provider_profile_ref');

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $name = self::profileNameFor($business);

        return $this->zernio->profileRefByName($name)
            ?? $this->zernio->createProfile($name, 'gbp-profile-'.$business->id);
    }

    /**
     * Carries the business id and nothing else — see the class docblock.
     */
    public static function profileNameFor(Business $business): string
    {
        return 'goaiez-business-'.$business->id;
    }

    private function markDisconnected(GbpConnection $connection, string $actor, string $reason): GbpConnection
    {
        return DB::transaction(function () use ($connection, $actor, $reason): GbpConnection {
            $accountRef = $connection->account_ref;

            $connection->status = GbpConnectionStatus::Disconnected;
            $connection->disconnected_at = now();
            $connection->last_checked_at = now();
            $connection->last_error = $reason;
            $connection->sync_cursor = null;

            $connection->save();

            if (is_string($accountRef) && $accountRef !== '') {
                $this->unbindAccount($accountRef);
            }

            $this->audit->record('gbp.disconnected', $actor, $connection, [
                'location_id' => $connection->location_id,
                'reason' => $reason,
            ]);

            return $connection;
        });
    }

    /**
     * Mirror the account ref into the platform index the webhook can read.
     *
     * ⛔ **THE RE-BIND CLEARS `revocation_owed_at`, AND THAT IS THE WHOLE OF
     * DECISION 5073.** This is an `updateOrCreate` keyed on `account_ref`, which
     * is unique — so a Google account connected again by a *different* business
     * rewrites `business_id` and `location_id` on the surviving row. Nothing
     * else in this system ever clears the stamp: the only "clear" is deleting
     * the row on a successful revocation. So a business erased with a failed
     * revocation left a stamp behind, and the moment the same Google account was
     * connected by somebody still paying — an owner who resubscribed, a
     * franchise, a sold location — that stamp named **them**. The nightly sweep,
     * and now an operator's Retry button, would then disconnect a live
     * customer's Google Business Profile: 4880 and 4884's harm reached from the
     * other end.
     *
     * ⚠️ **THIS IS THE POINT AT WHICH THE OBLIGATION IS PROVABLY DISCHARGED, NOT
     * FORGIVEN.** A tenant re-connecting is Zernio holding a grant it is
     * currently entitled to hold, on the account it currently belongs to. The
     * stale row is not evidence of anything after that; it is a loaded gun.
     *
     * ⛔ **AND THE MOVE NOW WRITES ITSELF DOWN — 6616 IS CLOSED HERE** (6760).
     * The paragraph above described a row silently changing hands and the code
     * beneath it did exactly that and nothing else: no audit entry, no activity
     * row, nothing an operator could review. **A cross-tenant move is the single
     * most sensitive thing this path can do** and it was the one act on it that
     * left no trace. {@see BINDING_MOVED} carries the argument for the name and
     * for the tenancy the record lands in.
     *
     * ⚠️ **THE READ IS BEFORE THE WRITE AND HAS TO BE.** `updateOrCreate()`
     * returns the row in its *new* state and Eloquent's `wasChanged()` cannot
     * answer for a row it fetched and updated in one call, so the previous
     * `business_id` is unrecoverable a line later. The extra `SELECT` is the
     * price of the record.
     *
     * ⚠️ **ONLY A CHANGE OF BUSINESS IS RECORDED, NOT A CHANGE OF LOCATION.**
     * The same tenant moving their own Google account between their own two
     * locations is a thing they are entitled to do, to their own data, and
     * `gbp.connected` already records it against the connection. Auditing it
     * here would put the ordinary act and the consequential one under one
     * action name, which is the filter problem {@see BINDING_MOVED} refuses.
     */
    private function bindAccount(string $accountRef, int $businessId, int $locationId, string $actor): void
    {
        $existing = GbpAccountBinding::query()->where('account_ref', $accountRef)->first();

        $binding = GbpAccountBinding::query()->updateOrCreate(
            ['account_ref' => $accountRef],
            [
                'business_id' => $businessId,
                'location_id' => $locationId,
                'revocation_owed_at' => null,
            ],
        );

        if ($existing === null || (int) $existing->business_id === $businessId) {
            return;
        }

        $this->audit->record(self::BINDING_MOVED, $actor, $binding, [
            'account_ref' => $accountRef,
            'from_business_id' => (int) $existing->business_id,
            'from_location_id' => (int) $existing->location_id,
            'to_business_id' => $businessId,
            'to_location_id' => $locationId,
            // ⚠️ **THE STAMP IS PART OF THE RECORD BECAUSE CLEARING IT IS PART
            // OF THE ACT** (5073). A move that discharges an outstanding
            // revocation obligation is a different event from one that does
            // not, and after the write above nothing anywhere remembers which
            // this was.
            'revocation_was_owed' => $existing->revocation_owed_at !== null,
        ]);
    }

    private function unbindAccount(string $accountRef): void
    {
        GbpAccountBinding::query()->where('account_ref', $accountRef)->delete();
    }
}
