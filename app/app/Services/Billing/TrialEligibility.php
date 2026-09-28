<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Console\Commands\PruneTrialOriginClaims;
use App\Enums\TrialClaimKind;
use App\Enums\TrialGrantRefusal;
use App\Exceptions\TrialGrantRefused;
use App\Models\Business;
use App\Models\Location;
use App\Models\TrialClaim;
use App\Services\Config\DefaultsRegistry;
use App\Services\Places\PlaceConfirmation;
use App\Services\Support\CreditGrants;
use App\Support\Trials\TrialGrantAuthorization;
use App\Support\Trials\TrialGrantVerdict;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The no-card trial's abuse controls (decision 2066, owed at 3117).
 *
 * ## What this is for
 *
 * The trial is fourteen days, no card, and it grants **500 real, billable SMS**
 * (2060, 2065). Decision 2066 calls that a fraud surface and requires its
 * controls "in the same slice as the grant, not after the first incident": one
 * trial per verified business, velocity limits, suppression and consent
 * unchanged.
 *
 * ⛔ **THE GRANT IS NOT BUILT AND MUST NOT BE BUILT HERE.** Nothing in `app/`
 * constructs `CreditKind::Grant` (3103) and a lint fails the build on anything
 * that does (3119), because minting an allowance means choosing 2060's figure as
 * an append-only ledger row while 2059's cap-versus-credits question is still
 * open (3115). Decision 3117's point is that the exposure today is zero **only by
 * accident** — it returns the moment somebody implements the funder, in a slice
 * whose stated job is something else. So the controls ship first, and the grant
 * lane inherits them instead of inventing them under deadline.
 *
 * ## What "one trial per verified business" actually keys off
 *
 * ⛔ **NOTHING IN THIS APPLICATION VERIFIES A BUSINESS.** Checked rather than
 * assumed, on 2026-08-13: `Features::emailVerification()` is commented out in
 * `config/fortify.php`, so not even the email address is confirmed;
 * `businesses.ein` and `businesses.legal_name` have **no writer anywhere** in
 * `app/`; `SupportSetting::forwarding_verified_at` is deliberately never written
 * (2913); and there is no domain-verification record of any kind. Writing "one
 * trial per verified business" over that would be `docs/FAILURE-SHAPES.md`'s
 * "protection layer asserted before it is true", which it records as having
 * bitten three
 * times.
 *
 * **The strongest honest key is a confirmed Google listing** — see
 * {@see TrialClaimKind::GoogleListing} for exactly what it does and does not
 * stop. In one line: it stops the same listing funding two accounts, and it does
 * not stop a stranger's listing funding one, because `PlaceConfirmation` records
 * that somebody *said* the listing was theirs and never asks Google.
 *
 * ## The three refusals, and what each costs when it is wrong
 *
 * {@see TrialGrantRefusal} carries the argument per case. The shape of all three
 * is the same and it is deliberate: **a refusal withholds an allowance and
 * nothing else.** No signal here refuses a registration, pauses an account,
 * blocks a send, or touches a consent record. A registration refused on a shared
 * network address is unrecoverable; a withheld allowance is one line of a support
 * operator's day ({@see CreditGrants}), which is why the
 * verdict is *surfaced* on Account 360 rather than only thrown.
 *
 * ## What this class deliberately does not do
 *
 * ⛔ **It reads no price, no allowance and no registry key.** Not one of
 * 2060–2066's eight figures has a `DefaultsManifest` entry and 3113 records that
 * as the correct state, because every one of them is a price the owner has not
 * reconciled against 2059's cap. The two constants below are neither — they are
 * engineering rails, sized in this file, and T137 §3 puts rate limiting under "no
 * owner decisions needed".
 *
 * ⛔ **It never touches consent or suppression** (2066, explicitly). STOP, HELP,
 * the Do Not Call register and every recorded sending basis behave identically
 * for a refused account and an eligible one. `TrialAbuseControlsTest` holds that
 * boundary as a lint in the direction that can fail today: no file under the
 * consent, compliance, messaging, SMS, reviews or campaigns services may
 * reference this class.
 *
 * ## Retention: indefinite, deliberately, and the reason is the unbuilt grant
 *
 * ⚠️ **THIS IS A DEPARTURE FROM THIS CODEBASE'S OWN 90-DAY PRECEDENT AND IT IS
 * RECORDED RATHER THAN QUIETLY TAKEN** (3235). `public_audits.ip_hash` gets 90
 * days and a sweep, and `PrunePublicAudits`' docblock is the rule this must
 * answer to: *"A retention column that nothing ever acts on is not a retention
 * policy — it is a claim."* `trial_claims` stores a derivative of the same
 * signal with no `expires_at`, no prune and no window.
 *
 * ⚠️ **THE OBVIOUS FIGURE IS `SIGNUP_ORIGIN_WINDOW_DAYS` AND IT IS WRONG**, which
 * is the part of the first version's argument that survived review: pruning at
 * thirty days does not age a burst out, it deletes the **anchor**, so the
 * account answers "no origin recorded" and is silently *eligible* on day
 * thirty-one — the exact failure 3224's anchored window exists to prevent.
 *
 * ⚠️ **WHAT DID NOT SURVIVE IS THE CONCLUSION THAT NO NUMBER COULD BE CHOSEN.**
 * The constants above are declared "an engineering rail and mine to set" on
 * T137 §3's authority, and a retention window on a hash-of-a-hash is the same
 * class of number; 502 is about **prices the owner owns**, and `CLAUDE.md`'s own
 * tie-break — *less stored PII* — points at a number rather than at indefinite.
 * The floor is derivable today without the funder: the trial plus the burst
 * window either side. {@see PruneTrialOriginClaims} sets
 * it at 180 days, derives it there, and is scheduled — so this is a policy
 * rather than a claim.
 *
 * ✅ **Erasure is covered and is a different question.** The foreign key
 * cascades, so deleting a tenant releases every claim it holds; nothing here
 * outlives the account it names. What accumulates is a keyed network hash per
 * *live* account, which is the narrowest form of the signal this application
 * knows how to keep. ⚠️ **The asymmetry the eventual prune has to respect**: a
 * signup-origin claim is dead weight once no grant can still be minted against
 * it, and a **listing claim must never be pruned** — it is the register, and
 * expiring one silently un-burns an identity.
 */
final class TrialEligibility
{
    /**
     * How many accounts may register from one network inside the window before
     * the allowance is withheld from the ones after it.
     *
     * ⚠️ **THIS NUMBER IS AN ENGINEERING RAIL AND IS MINE TO SET; THE ALLOWANCE
     * IT WITHHOLDS IS A PRICE AND IS NOT.** T137 §3 lists rate limiting under "no
     * owner decisions needed". 2060's 500 SMS, the AI credit grant and the
     * top-up prices are the owner's — and the *figures* are his to move as well,
     * which he did on 2026-08-24 (9180, 9181). **The amounts live in the seed
     * manifest and are deliberately not repeated here.**
     *
     * ⚠️ **SIZED TO BE WRONG RARELY RATHER THAN TO CATCH EVERYTHING** (511: a
     * lint tuned until it stops crying wolf is one tuned until it catches
     * nothing — and its inverse, a control tuned to catch everything, is one
     * whose refusals nobody believes). Three accounts in thirty days from one
     * address is a shared office, an agency onboarding its own clients, or a
     * carrier-grade NAT — all real and all uncommon. A signup script does forty
     * before lunch. The fourth account from one origin is withheld, not refused,
     * and a support operator can fund it in one action.
     */
    public const int SIGNUP_ORIGIN_ACCOUNT_LIMIT = 3;

    /**
     * The window the limit above is counted over.
     *
     * ⚠️ **ANCHORED TO THE ACCOUNT BEING ASKED ABOUT, NOT TO "NOW", AND THAT IS
     * LOAD-BEARING.** The verdict is asked at grant time, which may be weeks
     * after signup — a trailing thirty days from today would let a burst age out
     * of view and hand every account in it an allowance on day thirty-one, which
     * is exactly the patience a fraud script has. Counting claims within thirty
     * days *either side of this account's own signup* makes the finding a
     * permanent fact about the burst rather than a fact about when somebody
     * happened to ask.
     */
    public const int SIGNUP_ORIGIN_WINDOW_DAYS = 30;

    /**
     * Record that this account registered from this network.
     *
     * ⚠️ **THE CALLER SUPPLIES THE HASH, ALREADY KEYED**, and null is a real
     * answer rather than a placeholder — `HashedIp`'s own rule. A console
     * registration, a queued backfill and some proxy configurations have no
     * client address, and inventing one would make "we could not tell" and "they
     * all came from here" the same row.
     *
     * ⚠️ **AN ACCOUNT WITH NO ORIGIN CLAIM IS COUNTED IN NO BURST.** That is a
     * hole and it is the right one: the alternative is refusing every account
     * whose address we could not read, which over HTTP is a proxy
     * misconfiguration rather than a fraudster, and which would fail closed
     * against the wrong people. The listing key below is what carries the weight.
     */
    public function recordSignupOrigin(Business $business, ?string $originHash): void
    {
        if ($originHash === null) {
            return;
        }

        $this->claim($business, TrialClaimKind::SignupOrigin, $originHash);
    }

    /**
     * Record that this account confirmed a Google listing as its own.
     *
     * Written from {@see PlaceConfirmation::confirm()},
     * inside that method's transaction, so a listing confirmation and the claim
     * on it cannot come apart.
     *
     * ⚠️ **THE LOCATION IS REQUIRED AND IS WHAT MAKES THE CLAIM RELEASABLE**
     * (3234). A location's confirmations are a sequence and the newest one is
     * what the tenant actually has, so re-confirming a different listing writes
     * a superseding row and the mispasted one stops being scored — see
     * {@see self::liveListingClaims()}. Without it a typo burned a stranger's
     * listing permanently.
     */
    public function recordConfirmedListing(Business $business, Location $location, string $placeId): void
    {
        $this->claim($business, TrialClaimKind::GoogleListing, $placeId, $location);
    }

    /**
     * What the controls find about this account.
     *
     * Pure: it writes nothing and decides nothing beyond assembling the reasons.
     */
    public function verdictFor(Business $business): TrialGrantVerdict
    {
        $businessId = (int) $business->id;

        $refusals = array_values(array_filter([
            ...$this->listingRefusals($businessId),
            $this->signupOriginRefusal($businessId),
        ]));

        return new TrialGrantVerdict($businessId, $refusals);
    }

    /**
     * The verdict, as something a grant may be minted against.
     *
     * ⚠️ **THE ONE METHOD THE GRANT LANE IS MEANT TO CALL**, and the reason it
     * returns a type rather than a boolean is in
     * {@see TrialGrantAuthorization}'s docblock: a boolean invites an `if` with
     * an empty body, and a parameter cannot be forgotten by a caller who has to
     * hold one to call at all. **No lint can force that parameter to exist** —
     * an assertion about the callers of a method with no callers is vacuous
     * (256) — so what routes the next author here is `BillingTest`'s funding
     * lint (3119), which reddens on the first `CreditKind::Grant`.
     *
     * @throws TrialGrantRefused
     */
    public function authorize(Business $business): TrialGrantAuthorization
    {
        return new TrialGrantAuthorization($this->verdictFor($business));
    }

    /**
     * Whether this account holds any verification at all.
     *
     * Separate from the verdict because Account 360 shows it separately: "not
     * verified yet" and "verified, but somebody else got there first" are two
     * different conversations with a customer, and a single boolean would make an
     * agent guess which one they are having.
     */
    public function isVerified(Business $business): bool
    {
        return TrialClaim::query()
            ->where('business_id', (int) $business->id)
            ->where('kind', TrialClaimKind::GoogleListing)
            ->exists();
    }

    /**
     * The listing half of the verdict.
     *
     * ⚠️ **THE CROSS-TENANT READ THIS WHOLE TABLE EXISTS FOR.** Asking the same
     * question of `locations` would return zero rows for every account —
     * `locations` is `ENABLE`+`FORCE` row-level security on `app.business_id`,
     * and `withoutGlobalScopes()` does not reach beneath that (569). No scope is
     * dropped here and no policy was widened; the question is served from a store
     * that has no tenant.
     *
     * @return list<TrialGrantRefusal>
     */
    private function listingRefusals(int $businessId): array
    {
        $mine = $this->liveListingClaims(businessId: $businessId);

        if ($mine->isEmpty()) {
            return [TrialGrantRefusal::NoConfirmedListing];
        }

        /** @var list<string> $hashes */
        $hashes = $mine->pluck('identity_hash')->unique()->values()->all();

        // Everyone whose *live* listing is one of mine. Bounded by those hashes
        // rather than by the whole register — see liveListingClaims().
        $contenders = $this->liveListingClaims(identityHashes: $hashes);

        $tenures = $this->tenureStarts($contenders);

        foreach ($hashes as $hash) {
            $holders = $contenders->where('identity_hash', $hash);

            $minePriority = $holders
                ->where('business_id', $businessId)
                ->map(fn (TrialClaim $claim): int => $tenures[(int) $claim->id])
                ->min();

            $beaten = $holders->contains(
                fn (TrialClaim $other): bool => (int) $other->business_id !== $businessId
                    && $tenures[(int) $other->id] < $minePriority,
            );

            if ($beaten) {
                return [TrialGrantRefusal::ListingClaimedByAnother];
            }
        }

        return [];
    }

    /**
     * The newest listing claim per location — the live set.
     *
     * ⛔ **THIS IS WHAT MAKES A MISPASTED LISTING RECOVERABLE, AND WITHOUT IT THE
     * REGISTER WAS A TRAP** (3234). Scoring *every* historical claim meant an
     * owner who pasted the wrong business off a Maps results page burned a
     * stranger's place id permanently: correcting `locations.google_place_id`
     * released nothing, because the old claim went on being scored, and the real
     * owner of that listing was then refused by somebody else's typo forever.
     *
     * A location's confirmations are an append-only sequence and the last one is
     * what the tenant actually has, so the highest `id` per `location_id` is the
     * live claim and every earlier one is history. **Nothing is updated and
     * nothing is deleted** to achieve that.
     *
     * ⛔ **ALWAYS BOUNDED, AND THE FIRST VERSION OF THIS METHOD WAS NOT.** It took
     * no arguments and hydrated one row per location **for the whole platform**
     * on every call — and `AccountDirectory::snapshot()` calls it on every
     * support-console lookup, so an agent pressing resolve would have loaded the
     * entire register. The predicate the rewrite dropped is restored as a
     * required narrowing: a caller supplies either the business or the hashes.
     * The `max(id)` grouping stays a database aggregate over an index on
     * `(location_id, id)`; it is the *hydration* that had to be bounded.
     *
     * ⚠️ Claims whose `location_id` is null can never be live listing claims —
     * only a signup-origin claim has no location, and this set is listings.
     *
     * @param  list<string>|null  $identityHashes
     * @return Collection<int, TrialClaim>
     */
    private function liveListingClaims(?int $businessId = null, ?array $identityHashes = null): Collection
    {
        return TrialClaim::query()
            ->where('kind', TrialClaimKind::GoogleListing)
            ->whereNotNull('location_id')
            ->when($businessId !== null, fn ($query): mixed => $query->where('business_id', $businessId))
            ->when($identityHashes !== null, fn ($query): mixed => $query->whereIn('identity_hash', $identityHashes))
            ->whereIn('id', fn ($query): mixed => $query
                ->selectRaw('max(id)')
                ->from('trial_claims')
                ->where('kind', TrialClaimKind::GoogleListing->value)
                ->whereNotNull('location_id')
                ->groupBy('location_id'))
            ->get(['id', 'business_id', 'location_id', 'identity_hash']);
    }

    /**
     * Where each live claim's **current unbroken tenure** began, as a claim id.
     *
     * ⛔ **"WAS FIRST, EVER" WAS THE WRONG BOUNDARY AND THE SUPERSEDE FIX
     * INTRODUCED IT.** Scoring came from the live set and ranking came from the
     * whole history, so the two halves disagreed: an account could confirm X,
     * move away from it — releasing it, which is the whole point of superseding
     * — let somebody else take it and become eligible, then **re-confirm X and
     * win**, because its earliest claim was still the smallest id. That is a
     * revocable veto held forever, and it makes an already-eligible account
     * refused by a write it cannot see, cannot appeal and is never told about.
     * **A verdict that can go backwards is worse than one that is merely
     * strict.**
     *
     * So tenure, not history: the earliest claim in the unbroken run of claims
     * on the *same* listing ending at this location's live claim. Moving a
     * location away from a listing gives up priority on it, which is what
     * releasing has to mean if releasing means anything.
     *
     * ⚠️ **THE LISTING-MERGE CASE THAT MOTIVATED "FIRST EVER" IS STILL SERVED**,
     * which is why the narrower rule costs nothing. An account that re-confirms
     * the *same* listing has an unbroken run, so its tenure still starts at the
     * original claim and it still outranks a later arrival. And a merge changes
     * the place id, so it changes the hash — a different identity entirely,
     * unaffected either way.
     *
     * `id` is a sequence, so a smaller one is earlier without trusting a
     * timestamp, which a clock skew can reorder (3120–3126).
     *
     * @param  Collection<int, TrialClaim>  $live
     * @return array<int, int> live claim id => the id its tenure began at
     */
    private function tenureStarts(Collection $live): array
    {
        $locationIds = $live->pluck('location_id')->unique()->values()->all();

        if ($locationIds === []) {
            return [];
        }

        $history = TrialClaim::query()
            ->where('kind', TrialClaimKind::GoogleListing)
            ->whereIn('location_id', $locationIds)
            ->orderBy('id')
            ->get(['id', 'location_id', 'identity_hash'])
            ->groupBy('location_id');

        $starts = [];

        foreach ($live as $claim) {
            $rows = $history->get((string) $claim->location_id) ?? collect();

            $start = (int) $claim->id;

            // Walk back from the live claim while the listing is unchanged. The
            // first row of that run is where this tenure began.
            foreach ($rows->reverse() as $row) {
                if ((int) $row->id > (int) $claim->id) {
                    continue;
                }

                if ($row->identity_hash !== $claim->identity_hash) {
                    break;
                }

                $start = (int) $row->id;
            }

            $starts[(int) $claim->id] = $start;
        }

        return $starts;
    }

    /**
     * The velocity half.
     *
     * Counts **distinct accounts**, never rows. The partial unique index makes a
     * duplicate origin row impossible today; counting rows would silently become
     * wrong if that index were ever dropped, and it is exactly the kind of index
     * a later migration drops without reading this method.
     */
    private function signupOriginRefusal(int $businessId): ?TrialGrantRefusal
    {
        $mine = TrialClaim::query()
            ->where('business_id', $businessId)
            ->where('kind', TrialClaimKind::SignupOrigin)
            ->first();

        if (! $mine instanceof TrialClaim) {
            return null;
        }

        $anchor = $mine->created_at;

        $peers = TrialClaim::query()
            ->where('kind', TrialClaimKind::SignupOrigin)
            ->where('identity_hash', $mine->identity_hash)
            ->whereBetween('created_at', [
                $anchor->subDays($this->signupOriginWindowDays()),
                $anchor->addDays($this->signupOriginWindowDays()),
            ])
            ->distinct()
            ->count('business_id');

        return $peers > self::SIGNUP_ORIGIN_ACCOUNT_LIMIT
            ? TrialGrantRefusal::SignupOriginVelocity
            : null;
    }

    /**
     * Write one claim.
     *
     * ⚠️ **`insertOrIgnore` RATHER THAN A SELECT THEN AN INSERT** — decision
     * 350's lesson, where a check-then-insert held only sequentially and two
     * simultaneous posts both inserted. Two concurrent registrations from one
     * address are exactly that race. The partial unique index on the
     * signup-origin kind is the mechanism and this is the statement that
     * respects it.
     *
     * ⚠️ **AND `insertOrIgnore` RATHER THAN `updateOrCreate`**: the table is
     * append-only in both layers, and re-claiming an identity must not move its
     * timestamp — the timestamp is what the velocity window is measured from, so
     * a movable one is a window that can be walked out of.
     *
     * ⚠️ **A LISTING CLAIM IS UNDER NO UNIQUE INDEX, SO EVERY CONFIRMATION LEAVES
     * A ROW** — that is what superseding is made of (3234). The case that forces
     * it is a location confirming X, then Y, then X again: under a unique on
     * (business, kind, hash) the third write is swallowed, the newest row for
     * that location stays Y, and the register concludes the tenant's live
     * listing is one they moved away from. Rows are cheap and confirmations are
     * rare.
     */
    private function claim(Business $business, TrialClaimKind $kind, string $value, ?Location $location = null): void
    {
        DB::table('trial_claims')->insertOrIgnore([
            'business_id' => (int) $business->id,
            'location_id' => $location instanceof Location ? (int) $location->id : null,
            'kind' => $kind->value,
            'identity_hash' => self::identityHash($kind, $value),
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * The identity, as the only form this application is allowed to keep.
     *
     * ⚠️ **KEYED, AND DOMAIN-SEPARATED BY KIND.** Keyed for `HashedIp`'s reason —
     * an unkeyed hash of an IPv4 is the address with an extra step, because a
     * laptop enumerates 2^32 in minutes. Domain-separated because two kinds share
     * one column: without the prefix a place id and an address hash could in
     * principle collide into one another's namespace, and the resulting refusal
     * would be unexplainable from the row.
     *
     * ⛔ **AND THE KEY IS `APP_KEY`, SO ROTATING IT DISARMS THIS ENTIRE REGISTER
     * SILENTLY** (3236). Every hash becomes unmatchable at once: no duplicates,
     * no peers, every account eligible, and every previously-burned listing
     * quietly released. **No test can catch it** — the suite hashes with the same
     * key it compares against, so it stays green. `public_audits.ip_hash` has the
     * same property and does not matter in the same way, because it backs a
     * 90-day rate-limit signal rather than a durable fraud register. The
     * structural fixes are a dedicated never-rotated key or a stored key-version
     * column; neither is built, and `.claude/skills/deploying/` carries the
     * warning where a rotation would actually be typed.
     *
     * ⛔ **AND THE REGISTER IS NOT RE-DERIVABLE, WHICH 3236's OWN WORDING GOT
     * HALF WRONG ON THE DAY IT WAS WRITTEN.** It says the listing half could be
     * rebuilt from `locations.google_place_id`. That is true of the **set** and
     * false of the **ordering** — and the ordering is what a refusal turns on.
     * Since superseding, the verdict is decided by tenure starts read out of the
     * claim *history*, superseded rows and their relative ids included, and that
     * history exists nowhere but this table. A rebuild would reset every
     * business's priority to the rebuild order, silently re-deciding every
     * contested listing. The signup-origin half cannot be rebuilt at all, by
     * design: no raw address was ever stored.
     *
     * ⚠️ **THE SIGNUP-ORIGIN INPUT IS ALREADY A KEYED HASH** when it arrives, so
     * this is a hash of a hash. That is deliberate rather than sloppy: it means
     * this table's fingerprints cannot be joined against `public_audits.ip_hash`
     * or `magic_link_tokens.requested_ip_hash`, which are the same function of
     * the same address. ⚠️ **The second column was named `ip_hash` here until
     * 2026-08-22** (7903); the slip came from `HashedIp`'s own docblock, which
     * this paragraph was quoting, and is corrected there too. Those columns sit behind their own arguments; this one is readable
     * by anything with no tenant, and a shared value would let a reader of this
     * table learn which of our visitors became which of our accounts.
     */
    private static function identityHash(TrialClaimKind $kind, string $value): string
    {
        return hash_hmac('sha256', $kind->value.'|'.$value, (string) config('app.key'));
    }

    public function signupOriginWindowDays(): int
    {
        return app(DefaultsRegistry::class)->int('billing.trial.signup_origin_window_days');
    }
}
