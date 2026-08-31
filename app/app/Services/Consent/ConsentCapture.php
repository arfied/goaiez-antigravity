<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\ProofHashDomain;
use InvalidArgumentException;

/**
 * Everything needed to record one consent event.
 *
 * `29` §2: store wording version, timestamp, URL, IP hash and user agent — "not
 * a boolean". This object is that rule expressed as a constructor, so a consent
 * record missing its proof is unrepresentable rather than merely discouraged.
 *
 * It throws rather than returning null, which is the opposite of ConsentService::
 * permit(). The asymmetry is deliberate: refusing to send is safe and routine, so
 * it returns null; writing an undefendable consent record is neither, so it stops
 * the request.
 *
 * ⚠️ THE PROOF GUARDS NOW LIVE IN `ConsentProof` AND THIS CLASS'S PUBLIC SHAPE
 * IS UNCHANGED (2933). They moved out so `review_phi_consents` can reach the
 * same raw-IP walk and the same pre-checked-box refusal without inventing a
 * `ConsentType` for a record that is not about messaging at all. Everything
 * below is still this class's own: the wording version, and the rule that the
 * platform cannot own a consent record it never captured.
 *
 * ⚠️ **`$proof` IS NO LONGER THE ARRAY THAT WAS PASSED IN, AND THE PUBLIC SHAPE
 * IS STILL UNCHANGED.** `ConsentProof` now scopes `ip_hash` to the record's own
 * table ({@see ProofHashDomain}) instead of only refusing things, and
 * `ConsentService::record()` stores `$capture->proof` — so the property has to be
 * the scoped copy or the transformation would be built and discarded. The
 * parameter stays named `proof:` and every caller is unaffected; it is promoted
 * no longer, because a promoted readonly property cannot be replaced by the
 * constructor that would replace it.
 */
final readonly class ConsentCapture
{
    /**
     * The blob as it is allowed to be stored, scoped to `consent_records`.
     *
     * @var array<string, mixed>
     */
    public array $proof;

    /**
     * @param  array<string, mixed>  $proof  As captured — a raw `HashedIp` value,
     *                                       never a scoped one.
     */
    public function __construct(
        public CapturedBy $capturedBy,
        public CaptureSurface $captureSurface,
        public ConsentType $consentType,
        public string $disclosureVersion,
        public string $method,
        array $proof,
    ) {
        if (trim($this->disclosureVersion) === '') {
            throw new InvalidArgumentException(
                'A consent record needs the disclosure version the person actually saw.',
            );
        }

        // Padding is rejected rather than trimmed away. Two records showing the
        // same wording have to compare equal, and a version stored as
        // ' 2026-07-01 ' does not — silently fixing it here would hide the caller
        // bug that produced it and leave the mismatch to surface in an export.
        if (trim($this->disclosureVersion) !== $this->disclosureVersion) {
            throw new InvalidArgumentException(
                'The disclosure version is padded with whitespace. Pass it exactly as it '
                .'is published, so two records of the same wording compare equal.',
            );
        }

        // Every guard about the blob itself, in one place — see ConsentProof.
        // ⚠️ AND IT IS KEPT NOW: that class scopes `ip_hash` as well as refusing
        // things, so discarding it here would have left `ConsentService` storing
        // the unscoped hash with nothing to say so.
        $this->proof = (new ConsentProof(
            $proof,
            $this->captureSurface,
            $this->method,
            ProofHashDomain::ConsentRecords,
        ))->proof;

        $this->refuseAccountHolderSurface();
        $this->requirePlatformCaptureBeOurs();
    }

    /**
     * ⛔ THE CARD FORM, THE SIGNUP FORM AND THE OWNER-NOTIFY SCREEN ARE NOT
     * CONSENT SURFACES (2980–2999; T176 P22, R24, 10540).
     *
     * `CaptureSurface::Checkout` exists so the auto-renewal acknowledgment can
     * reach `ConsentProof`'s guards without a second copy of them,
     * `CaptureSurface::Signup` exists so the signup terms acceptance can do the
     * same, and `CaptureSurface::OwnerNotify` exists so the owner-channel
     * consent capture (10540) can do the same again. All three are
     * `isSelfRendered()`, because we do render those pages — which is exactly
     * what makes them dangerous here: any one of them would satisfy
     * `requirePlatformCaptureBeOurs()` below and mint a **Lane A**
     * platform-captured basis to text somebody, out of a form nobody ever agreed
     * to be contacted on.
     *
     * ⚠️ **AND THE PERSON ON ALL THREE IS THE ACCOUNT HOLDER, NOT A
     * CUSTOMER.** R24 is an owner ruling of record — terms are for tenants,
     * never for end customers — and 10540 extends the identical reasoning to
     * the owner's own consent to be texted about their own account: a
     * `consent_records` row carrying any of the three would name the tenant's
     * own owner as one of their own contacts. `OwnerNotify`'s real record is
     * `owner_notification_consents`, which has no `customer_id` column to put
     * one in.
     *
     * ⚠️ REFUSED HERE RATHER THAN BY MAKING `isSelfRendered()` FALSE. That would
     * have been the one-word version and it would have loosened the proof
     * requirement on those three records instead — url, ip_hash and user_agent
     * would all have become optional on the records that exist to prove what was
     * on the screen.
     */
    private function refuseAccountHolderSurface(): void
    {
        if (! in_array(
            $this->captureSurface,
            [CaptureSurface::Checkout, CaptureSurface::Signup, CaptureSurface::OwnerNotify],
            true,
        )) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'The %s is not a consent surface. `CaptureSurface::%s` belongs to an '
                .'account-holder record which permits no contact of any kind — a consent '
                .'record carrying it would be a platform-captured Lane A basis manufactured '
                .'out of a form the tenant\'s own owner filled in.',
                match ($this->captureSurface) {
                    CaptureSurface::Checkout => 'card form',
                    CaptureSurface::Signup => 'signup form',
                    default => 'owner-notify screen',
                },
                $this->captureSurface->name,
            ),
        );
    }

    /**
     * The platform cannot own a consent record it never captured.
     *
     * `CapturedBy::lane()` maps `platform` to Lane A, and Lane A is one shared
     * toll-free number under the GO AI EZ brand. `CaptureSurface`'s docblock has
     * always said the attestation surfaces "land on Lane B and need the tenant's
     * own brand registration" — but nothing enforced the pairing, so
     * `new ConsentCapture(Platform, Import, ...)` constructed happily and derived
     * Lane A for a contact from a spreadsheet.
     *
     * This is the slice's own standard applied to its own gap: an invariant
     * stated in a comment is one somebody can violate without noticing.
     */
    private function requirePlatformCaptureBeOurs(): void
    {
        if ($this->capturedBy !== CapturedBy::Platform) {
            return;
        }

        if ($this->captureSurface->isSelfRendered()) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Consent captured on %s is the tenant asserting something that happened '
                .'elsewhere, so it is captured_by = tenant and rides Lane B. Only a surface '
                .'this application rendered can be captured_by = platform, because Lane A '
                .'sends from the shared platform number under our own disclosure.',
                $this->captureSurface->value,
            ),
        );
    }
}
