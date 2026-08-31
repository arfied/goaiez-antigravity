<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Enums\ReviewDestination;
use App\Models\Business;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Billing\TrialEligibility;
use App\Services\Compliance\TenantClassification;
use App\Services\Destinations\DestinationSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only thing that writes a resolved `place_id` to a location.
 *
 * `24` §1.2.3, which is a build-failing test (`17` GBP-01b): "**Never save a
 * resolved `place_id` without that confirmation**, however confident the match.
 * A wrong `place_id` invites real customers to review a stranger's business, and
 * every invite after it is wrong in the same direction."
 *
 * THE CONFIRMATION IS A REQUIRED ARGUMENT, NOT A CHECK. There is no code path
 * through this class that writes without one, because the alternative — an
 * `if ($confirmed)` somewhere — is a line somebody can delete and no test would
 * necessarily notice. Here, deleting it does not compile.
 *
 * WHAT GETS KEPT, AND WHY EACH. `24` §1.2.3 requires the audit row to carry "the
 * pasted URL, which row resolved it, the resolved `place_id`, the `google_cid`,
 * the actor, and the timestamp", and the location keeps the pasted URL too:
 *
 *   google_place_id   the invite link's input. **Changes on listing merges.**
 *   google_cid        the permanent identifier, per DATA-MODEL §5.2's note
 *   google_maps_url   the raw pasted URL — the re-resolution input when the
 *                     place id goes stale (decisions 105-108)
 *
 * The last one is the non-obvious one. When a place id breaks, having the
 * original link means re-running the ladder rather than asking the owner to
 * paste again — and asking again is a message to someone who thought they were
 * finished.
 *
 * NOT A CONFIRM CATEGORY. `24` §1.2.3 closes by saying so explicitly: this is a
 * setup step, and rule 1 reserves CONFIRM for GBP field changes, spending money,
 * and the first send of a new campaign type.
 *
 * IT ALSO ENABLES GOOGLE AS A REVIEW DESTINATION (decision 312). That looks like
 * an unrelated responsibility and is not: `review_destinations` holds no Google
 * link because the link is derived from `google_place_id`, so the row cannot
 * honestly be enabled until this method runs. Slice B considered a hook on
 * provisioning instead and could not have both "provisioning enables Google" and
 * "enabling Google without a place_id throws" — this method already has the
 * actor, the transaction and the audit entry, so the enable rides a tested path.
 *
 * IT ALSO CLASSIFIES THE TENANT, on the same argument and for a larger stake —
 * see raiseClassificationIfHealthcare(). Confirming a listing is the one moment
 * in signup where Google's own categories for *this* business are known to be
 * about this business, because a human has just said so.
 */
final class PlaceConfirmation
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly DestinationSettings $destinations,
        private readonly TenantClassification $classification,
        private readonly TrialEligibility $trials,
    ) {}

    /**
     * Persist a confirmed candidate against a location.
     *
     * @param  string  $pastedUrl  The original input, kept for re-resolution.
     * @param  string  $actor  An actor label — 'user:14', never a bare id.
     * @param  true  $confirmed  The owner said "Yes, that's us".
     *
     * `$confirmed` is typed `true`, not `bool`, and that is the whole
     * enforcement mechanism. PHP 8.2's literal `true` type means a caller
     * holding a plain boolean **cannot call this method** — they have to narrow
     * it first, which is the moment they must actually check. An
     * `if ($confirmed)` inside would be a line somebody could delete with no
     * test necessarily noticing; this one fails static analysis and throws a
     * TypeError at runtime, without any code of ours being involved.
     *
     * @throws RuntimeException when the candidate has nothing a human could
     *                          have confirmed.
     */
    public function confirm(
        Location $location,
        PlaceCandidate $candidate,
        string $pastedUrl,
        string $actor,
        true $confirmed,
    ): Location {
        if (! $candidate->isConfirmable()) {
            // Asking someone to confirm an opaque identifier is not asking them
            // to confirm anything. A candidate with no name and no address has
            // not been confirmed by a human, whatever the flag says.
            throw new RuntimeException(
                'This candidate carries nothing a person could recognise, so the confirmation '
                .'it claims cannot have happened. Resolve a display name first.'
            );
        }

        return DB::transaction(function () use ($location, $candidate, $pastedUrl, $actor): Location {
            $before = [
                'google_place_id' => $location->google_place_id,
                'google_cid' => $location->google_cid,
            ];

            $location->forceFill([
                'google_place_id' => $candidate->placeId,
                // Only ever set, never cleared: a CID we already hold is
                // permanent, and a resolution that produced none (rows 1-2)
                // must not erase one an earlier resolution found.
                'google_cid' => $candidate->googleCid ?? $location->google_cid,
                'google_maps_url' => $pastedUrl,
            ])->save();

            $this->audit->record(
                'location.place_id_confirmed',
                $actor,
                $location,
                [
                    'pasted_url' => $pastedUrl,
                    'resolution_rule' => $candidate->rule->value,
                    'place_id' => $candidate->placeId,
                    'google_cid' => $candidate->googleCid,
                    'confirmation_label' => $candidate->confirmationLabel(),
                    'before' => $before,
                ],
            );

            // GOOGLE BECOMES A REVIEW DESTINATION HERE, not at provisioning
            // (decision 312). Its invite link is derived from the place id, so
            // this is the first moment there is anything to derive it from —
            // enabling any earlier would mean an enabled destination with a
            // broken link, discovered by a customer rather than by us.
            //
            // In the same transaction as the id itself, deliberately: a
            // committed place id with an unenabled Google row is a tenant who
            // pasted their listing and got nothing, and there is no later step
            // that would notice.
            $this->destinations->enable($location, ReviewDestination::Google, null, $actor);

            $this->raiseClassificationIfHealthcare($candidate, $actor);

            // ⚠️ THE ONLY MOMENT THIS APPLICATION VERIFIES A BUSINESS AT ALL, so
            // it is the moment the trial's abuse controls get their strongest
            // key (decision 2066, owed at 3117). Nothing else in `app/` confirms
            // an identity: email verification is switched off, `businesses.ein`
            // has no writer, and `forwarding_verified_at` is never written.
            //
            // ⚠️ WHAT IS RECORDED IS A KEYED HASH OF THE PLACE ID, NOT THE PLACE
            // ID. `trial_claims` is readable with no tenant established, by
            // design, and *which listing belongs to which account* is a tenant
            // fact — see the creating migration.
            //
            // ⛔ THIS CONFIRMS OWNERSHIP OF NOTHING AND MUST NOT BE DESCRIBED AS
            // IF IT DID. This method's own contract is that a human said "yes,
            // that's us"; it never asks Google. So the claim stops the same
            // listing funding two accounts and does not stop a stranger's
            // listing funding one. `TrialClaimKind::GoogleListing` carries the
            // full statement of that limit.
            //
            // In the same transaction as the id itself, on decision 312's
            // reasoning one line up: a committed place id whose claim was lost
            // is an identity that silently stops being spent-once.
            // ⚠️ **THIS METHOD NOW REQUIRES A TENANT UNCONDITIONALLY, WHERE IT
            // PREVIOUSLY ONLY DID SO FOR A HEALTHCARE CATEGORY.**
            // `raiseClassificationIfHealthcare()` reaches `Business::query()`
            // behind an early return, so a caller with no tenancy established
            // used to get through for an ordinary business; the line below has
            // no such branch and `sole()` throws without one. That is the right
            // requirement — a claim with no tenant is meaningless — but it is a
            // tightened precondition rather than an addition, and every caller
            // in `app/` already establishes tenancy.
            //
            // ⚠️ **AND A REPEATED WIZARD SUBMISSION WRITES A ROW EACH TIME.**
            // Idempotent in meaning — superseding makes a re-confirmation of the
            // same listing a no-op for every verdict — and not in storage, which
            // is the trade superseding is built on (3234). Confirmations are a
            // setup step, so the row count is bounded by human patience rather
            // than by anything automated.
            $this->trials->recordConfirmedListing(
                // sole(), for `raiseClassificationIfHealthcare()`'s reason:
                // `Business` is scoped on its own primary key, so this is the
                // tenant in context and cannot be another one.
                Business::query()->sole(),
                $location,
                $candidate->placeId,
            );

            return $location;
        });
    }

    /**
     * Raise this tenant to PHI handling when Google's own categories say so.
     *
     * ⚠️ WHY THIS LIVES HERE AND NOT ON THE WIZARD. Decision 477 corrected 469:
     * the primary classification signal is not owner-blocked, because the wizard
     * already confirms a Google listing for every tenant — including the
     * audit-less signups the provisioning path cannot reach at all. Decision 465
     * had left those permanently `pii`, and 477 states the residual exactly: a
     * healthcare practice that registers without an audit token has its patients'
     * free-text reviews sent to Anthropic or OpenAI with **no BAA in place**.
     *
     * It rides on `confirm()` for the reason decision 312 put the Google
     * destination here: this method already holds the actor, the transaction and
     * the audit entry, and it is the only thing that can say a human looked at
     * this listing and said "yes, that's us". Putting it on `FindBusiness`
     * instead would make the classification a property of one screen — and
     * decisions 391 and 398 are this codebase's record of what happens next,
     * which is a second caller reaching the service and skipping the guard.
     *
     * ⚠️ IT ONLY EVER RAISES, AND THE EARLY RETURN IS NOT A SECOND DECISION. The
     * decider is `TenantClassification`; this asks it and acts on the answer.
     * What the guard encodes is that service's own asymmetry, which
     * `reclassify()` enforces by throwing: a wrongly raised tenant loses AI
     * analysis and can be put back, a wrongly lowered one has patient text on the
     * wire to a vendor we hold no BAA with and cannot. So a listing that matches
     * nothing is silently no action — never a downgrade — and that is also what
     * makes an empty category list safe, which is the state every row-1 candidate
     * is in (see `PlaceCandidate`).
     *
     * ⚠️ AND IT NARROWS THE EXPOSURE RATHER THAN CLOSING IT. A tenant who skips
     * this wizard step, or who confirms through the ladder's row 1, still gets no
     * signal from here. What remains after this is COMP-02's wizard question —
     * a person answering directly — which needs owner sign-off on its wording
     * (469, 477). Do not read a green test on this as coverage.
     *
     * Already-PHI tenants cost nothing: `reclassify()` returns early when the
     * classification is unchanged and writes neither a column nor a log row,
     * which is decision 290's rule and the reason this needs no "is it already?"
     * read of its own.
     */
    private function raiseClassificationIfHealthcare(PlaceCandidate $candidate, string $actor): void
    {
        $matched = $this->classification->phiCategory($candidate->categories);

        if ($matched === null) {
            return;
        }

        $this->classification->reclassify(
            // sole(), because `Business` is scoped on its own primary key
            // (IsTenantRoot) and `reclassify()` refuses a business that is not
            // the tenant in context — it files the audit entry against the acting
            // business, so a mismatch would land the record in the wrong log.
            Business::query()->sole(),
            $this->classification->forCategories($candidate->categories),
            $actor,
            // The matched category, not prose restating the outcome. This string
            // is the only record of why this tenant's data handling changed, and
            // "Google lists this business as dental_clinic" is answerable a year
            // later by someone looking at the listing.
            'Google lists this business as '.$matched.', confirmed by the owner during setup.',
        );
    }
}
