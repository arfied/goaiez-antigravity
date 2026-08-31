<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingTerm;
use App\Enums\CaptureSurface;
use App\Enums\ImpersonationCapability;
use App\Enums\ProofHashDomain;
use App\Models\AutoRenewalAcknowledgement;
use App\Services\AuditService;
use App\Services\Consent\ConsentProof;
use App\Services\Impersonation\Impersonation;
use Illuminate\Support\Facades\DB;

/**
 * The record that the account holder was told the plan renews (2980–2999).
 *
 * ⛔ **THE CHECKOUT PAGE PROMISED "you can cancel any time" AND THE APPLICATION
 * HAD NO CANCELLATION AT ALL.** That is the finding this whole slice starts
 * from, and it is why this class exists rather than a boolean: California's
 * Automatic Renewal Law wants three things — the terms acknowledged
 * separately, a reminder before renewal, and a cancellation mechanism at least
 * as easy as the signup — and a codebase that had none of them had *prose*
 * claiming the third.
 *
 * ## Why it reuses `ConsentProof` rather than checking its own blob
 *
 * 2933 extracted those guards out of `ConsentCapture` for exactly this: a
 * second kind of record that needs the same proof shape and must not carry a
 * `ConsentType` it would be lying about. `29` §2 rule 21 — never store a raw
 * IP — is absolute, and a second implementation of an absolute rule is one that
 * will diverge from the first. `rejectRawIp()` has already had to be rewritten
 * once, from a denylist of key names to a walk of every string at every depth,
 * and the rewrite would have reached one copy.
 *
 * ⚠️ **THE SURFACE IS `CaptureSurface::Checkout`, WHICH IS NEW AND WHICH
 * `ConsentCapture` REFUSES BY NAME.** `isSelfRendered()` answers true for it —
 * we serve that page, so the URL, the hashed address and the user agent are all
 * in hand and their absence means somebody skipped a step. That is the whole
 * reason for a new case rather than borrowing `FeedbackPage`: the guard has to
 * bite, and the enum has to stay honest about which surface it names.
 *
 * ## Who calls this, and the one purchase path that does not
 *
 * `AuthorizeNetCheckoutController::store()`, immediately before the gateway
 * call — the primary gateway (2056) and the page that carried the *"you can
 * cancel any time"* sentence this lane started from.
 *
 * ⛔ **THE STRIPE PATH RECORDS NOTHING, AND THAT IS A GAP RATHER THAN A
 * DESIGN.** `GET /billing/checkout` is a redirect with no page of ours:
 * `RegisterResponse` sends a newly registered person straight to it and
 * `BillingController::checkout()` opens a hosted Session and hands the browser
 * to Stripe. There is no box of ours to tick, and a `CaptureSurface::Checkout`
 * proof — url, hashed address, user agent — cannot honestly be asserted about a
 * page Stripe rendered. **Closing it means either an interstitial before the
 * redirect, which decision 690's docblock refuses on drop-off grounds at the
 * costliest step of signup, or moving registration onto the gateway 2056
 * already calls primary.** Both are product calls rather than engineering ones,
 * so the gap is written down (2980–2999) instead of guessed at. ⚠️ **Do not
 * read the absence of a row as the absence of a duty.**
 *
 * ## What a row here does not do
 *
 * It is not a cancellation, it is not a subscription state, and it is not proof
 * that anybody read anything. It is evidence of a disclosure made at a moment,
 * in words we can still produce.
 */
final class AutoRenewalAcknowledgements
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * Record one acknowledgment, with its proof.
     *
     * Modelled on `PhiAnalysisConsent::record()`, which is the live caller of
     * the other non-consent kind. Same shape: the wording version and the words
     * themselves are read off {@see AutoRenewalDisclosure} so the stored text is
     * exactly what the page rendered, `checkbox_state` is stamped here so
     * `ConsentProof` can refuse anything that is not a box a person ticked, and
     * the proof is validated **before** the row exists rather than after — which
     * is what makes an unproved record unrepresentable rather than merely
     * unusual.
     *
     * @param  bool  $renews  Whether this purchase auto-renews at all. Decides
     *                        which version and which words, because an
     *                        instalment plan does not renew (2749) and the
     *                        renewing wording would be false on it.
     * @param  array<string, mixed>  $proof  url, ip_hash, user_agent.
     */
    public function record(
        BillingTerm $term,
        bool $renews,
        string $price,
        array $proof,
        string $actor,
    ): AutoRenewalAcknowledgement {
        // ⚠️ SUPPORT CANNOT ACKNOWLEDGE TERMS ON SOMEBODY'S BEHALF — the reason
        // `AcceptLegalDocuments` already gives itself: "an acceptance recorded
        // from here would not be one". An agent holding a live act-as session
        // who completed a tenant's checkout would otherwise manufacture the one
        // record that says the buyer was told the price recurs.
        $this->impersonation->refuse(ImpersonationCapability::AcceptLegalDocuments);

        $version = AutoRenewalDisclosure::versionFor($renews);

        $blob = $proof + [
            'checkbox_state' => 'checked_by_user',
            'disclosure_text' => AutoRenewalDisclosure::textFor($renews, $price, $term),
            'disclosure_label' => AutoRenewalDisclosure::labelFor($renews),
        ];

        // Constructed for its guards AND for what it returns — the scoped
        // array is what the column stores, with `ip_hash` re-hashed under this
        // table's own domain so it cannot be joined against anybody else's copy
        // of the same address (7888, {@see ProofHashDomain}).
        $blob = (new ConsentProof(
            $blob,
            CaptureSurface::Checkout,
            'checkbox',
            ProofHashDomain::AutoRenewalAcknowledgements,
        ))->proof;

        return DB::transaction(function () use ($term, $version, $blob, $actor): AutoRenewalAcknowledgement {
            $acknowledgement = AutoRenewalAcknowledgement::create([
                'disclosure_version' => $version,
                'method' => 'checkbox',
                'term' => $term,
                'proof' => $blob,
                'created_at' => now(),
            ]);

            // NO PROOF BLOB in the metadata: the audit log is read by more
            // people than this table is, and the proof is one lookup away
            // through the entity reference.
            $this->audit->record('billing.auto_renewal_acknowledged', $actor, $acknowledgement, [
                'disclosure_version' => $version,
                'term' => $term->value,
            ]);

            return $acknowledgement;
        });
    }

    /**
     * Has this tenant ever acknowledged the renewal terms?
     *
     * Tenant-scoped by the global scope on the model, like every other read
     * here. ⚠️ **It answers "ever", not "for the current subscription"**, and
     * the difference is deliberate: the acknowledgment is evidence of a
     * disclosure made at a moment, and a tenant who bought, cancelled and came
     * back has two rows and needed both.
     */
    public function exists(): bool
    {
        return AutoRenewalAcknowledgement::query()->exists();
    }
}
