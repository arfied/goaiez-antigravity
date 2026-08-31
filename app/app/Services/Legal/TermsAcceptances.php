<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Enums\CaptureSurface;
use App\Enums\ImpersonationCapability;
use App\Enums\LegalDocumentType;
use App\Enums\ProofHashDomain;
use App\Enums\TermsAcceptanceMethod;
use App\Models\Business;
use App\Models\TermsAcceptance;
use App\Services\AuditService;
use App\Services\Consent\ConsentProof;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The record of which legal-document versions a business accepted, and when
 * (T176 P22).
 *
 * ⛔ **THERE WAS NO SUCH RECORD ANYWHERE** — `terms_accepted` had zero
 * occurrences in `app/`, `database/`, `routes/` and `resources/` — while the
 * signup form asked nobody to accept anything at all. Carrier reviewers and
 * counsel both expect this, and it is the one question a signup dispute opens
 * with: *what did you show them, and which version was it?*
 *
 * ⛔ **TENANT SIGNUP ONLY (terms are not customer consent).** The account holder accepts; an end customer
 * never sees a terms flow anywhere. Nothing here takes a customer, the table has
 * no column that could name one, and `CaptureSurface::Signup` is refused by
 * `ConsentCapture` outright so this can never be mistaken for a basis to
 * contact anybody.
 *
 * ## Why it reuses `ConsentProof` rather than checking its own blob
 *
 * 2933 extracted those guards out of `ConsentCapture` for exactly this: a
 * further kind of record that needs the same proof shape and must not carry a
 * `ConsentType` it would be lying about. `29` §2 rule 21 — never store a raw IP
 * — is absolute, and a second implementation of an absolute rule is one that
 * will diverge from the first.
 *
 * ## Who calls this — both doors, deliberately
 *
 * `CreateNewUser::create()` for the password form and `OauthLoginController`
 * for a first Google or Microsoft sign-in. Those are the only two things in
 * this application that create a tenant, and guarding one and not the other is
 * what `RegistrationRateLimits` did until 3233 corrected it — a control that
 * covers half the doors reads exactly like one that covers them all. A lint
 * asserts every caller of `TenantProvisioner::provision()` also records an
 * acceptance.
 *
 * ⚠️ **THE TWO DOORS RECORD DIFFERENT STRENGTHS AND SAY WHICH.** The form has a
 * box the person ticked; the SSO buttons carry a notice beside them and the act
 * is pressing the button. `TermsAcceptanceMethod` is what tells them apart, and
 * the proof blob's URL on the SSO path is the **callback** address rather than
 * the page that rendered the notice — one redirect earlier, and there is no
 * honest way to claim otherwise from inside the callback.
 *
 * ## Who reads this, and why the read is not an ordinary query
 *
 * `Admin\TermsAcceptances`, and nothing else in `app/`. The record's readers are
 * a person answering a carrier's question and a person answering a subpoena, and
 * neither of those is this application's own logic — nothing here decides
 * anything, ever. A lint fails the build on any second file touching the model,
 * so a screen that wanted its own query would redden rather than arrive.
 *
 * ⛔ **THE READ CARRIES A TENANT GUARD AND THE WRITE DOES NOT NEED ONE.**
 * `record()` fills `business_id` from ambient context, so it cannot name the
 * wrong business; a read is handed one and answers under whatever tenant is
 * established, which is the *wrong tenant* case row-level security cannot catch.
 * See {@see self::assertIsTenant()}.
 *
 * ⛔ **AND THE READ PATH EXPOSES NO WRITE.** Both readers return models whose
 * `updating` and `deleting` hooks throw, so "read-only" is a property of the
 * record rather than a promise made by this class.
 */
final class TermsAcceptances
{
    public function __construct(
        private readonly SignupTerms $terms,
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * Record one business's acceptance of every signup document, with its proof.
     *
     * ⚠️ **CALLED INSIDE THE REGISTRATION TRANSACTION, AFTER THE TENANT
     * EXISTS.** `BelongsToTenant` fills `business_id` from
     * `Tenancy::idOrFail()`, and `TenantProvisioner::provision()` is what
     * establishes it — so this runs after provisioning and rolls back with it.
     * An account whose terms acceptance failed to write is not a partly set-up
     * account; it is an account with no agreement behind it.
     *
     * ⚠️ **THE PROOF IS VALIDATED BEFORE ANY ROW EXISTS**, which is what makes
     * an unproved record unrepresentable rather than merely unusual — the
     * property `ConsentCapture` and `AutoRenewalAcknowledgements` both have.
     *
     * @param  array<string, mixed>  $proof  url, ip_hash, user_agent.
     * @return list<TermsAcceptance>
     *
     * @throws SignupTermsUnavailable
     */
    public function record(TermsAcceptanceMethod $method, array $proof, string $actor): array
    {
        // ⚠️ SUPPORT CANNOT ACCEPT TERMS ON SOMEBODY'S BEHALF — the reason
        // `ImpersonationCapability::AcceptLegalDocuments` gives itself: "an
        // acceptance recorded from here would not be one". Nobody holds an
        // act-as session during registration today, and the guard is here
        // anyway, because the day something creates a tenant from inside a
        // support session is the day this record would be manufactured.
        $this->impersonation->refuse(ImpersonationCapability::AcceptLegalDocuments);

        $documents = $this->terms->requireCurrent();

        // ⚠️ **THE TWO STAMPED KEYS OVERWRITE THE CALLER'S RATHER THAN
        // DEFERRING TO THEM**, which is the opposite way round from
        // `AutoRenewalAcknowledgements` and deliberate: `checkbox_state` is what
        // `ConsentProof` refuses a record on, and `disclosure_text` is the
        // wording an acceptance claims was shown. A caller that could supply
        // either could file acceptance by notice as a ticked box, or record
        // words no screen ever rendered.
        $blob = array_filter([
            // Only a box a person ticked carries this, and `ConsentProof`
            // refuses any other value when the method is a checkbox. The SSO
            // path deliberately has none — there is no box, and stamping one
            // would record the weaker act as though it were the stronger.
            'checkbox_state' => $method === TermsAcceptanceMethod::Checkbox ? 'checked_by_user' : null,
            'disclosure_text' => $method->notice(),
        ], static fn (mixed $value): bool => $value !== null)
            + Arr::except($proof, ['checkbox_state', 'disclosure_text']);

        // Constructed for its guards AND for what it returns — the scoped
        // array is what the column stores. `CaptureSurface::Signup` is
        // `isSelfRendered()`, which is what makes url, ip_hash and user_agent
        // mandatory rather than optional: we serve both signup surfaces, so
        // their absence means somebody skipped a step.
        //
        // ⚠️ **AND THE HASH THAT COMES BACK IS NOT THE ONE THAT WENT IN.** This
        // record is written on every registration through either door, so it is
        // the *named* side of 7888's join — a business, an owner, and the
        // network they opened the account from. `ProofHashDomain` carries why
        // that pairing is the expensive one.
        $blob = (new ConsentProof(
            $blob,
            CaptureSurface::Signup,
            $method->value,
            ProofHashDomain::TermsAcceptances,
        ))->proof;

        return DB::transaction(function () use ($documents, $method, $blob, $actor): array {
            $accepted = [];

            foreach (SignupTerms::DOCUMENTS as $type) {
                $document = $documents[$type->value];

                $accepted[] = $acceptance = TermsAcceptance::create([
                    'doc_type' => $type,
                    'legal_document_id' => $document->getKey(),
                    'version' => $document->version,
                    'method' => $method,
                    'accepted_by' => $actor,
                    'proof' => $blob,
                    'created_at' => now(),
                ]);

                // NO PROOF BLOB in the metadata: the audit log is read by more
                // people than this table is, and the proof is one lookup away
                // through the entity reference.
                $this->audit->record('legal.terms_accepted', $actor, $acceptance, [
                    'doc_type' => $type->value,
                    'version' => $document->version,
                    'method' => $method->value,
                ]);
            }

            return $accepted;
        });
    }

    /**
     * Everything this business has ever accepted, newest first.
     *
     * ⚠️ **THE READER ARRIVED WITH ITS CALLER, WHICH IS WHY IT IS HERE NOW.**
     * This method's place in this file held a note saying there was deliberately
     * no reader — *"its readers are a person answering a carrier's question and
     * whatever screen a later slice builds for them"*. `Admin\TermsAcceptances`
     * is that screen, and this is that read. A read method with no caller is the
     * decoration `CLAUDE.md` warns about from the other direction, so the two
     * land together or neither does.
     *
     * ⚠️ **THE WHOLE HISTORY, NOT THE STANDING POSITION.** A business accepts
     * three documents at signup and would accept a fourth row the day counsel
     * publishes a new version of one of them — so "what has this tenant agreed
     * to" and "what did they agree to in August" are different questions, and
     * only the full list answers the second. {@see self::latestFor()} answers
     * the first, per document.
     *
     * ⛔ **`id` DESCENDING, NOT `created_at` DESCENDING.** The three rows of one
     * signup are written inside a single transaction with the same `now()`, so
     * ordering by the timestamp leaves their order to whatever the database
     * feels like — and the screen's first row would then be an arbitrary one of
     * the three. `id` is the only column this codebase orders descending on
     * ({@see BaaRecords::forBusiness()}), and here it is also the only one that
     * is total.
     *
     * @return list<TermsAcceptance>
     */
    public function historyFor(Business $business): array
    {
        $this->assertIsTenant((int) $business->id);

        return array_values(TermsAcceptance::query()->orderByDesc('id')->get()->all());
    }

    /**
     * Which version of ONE document this business accepted, and when.
     *
     * ⚠️ **ONE DOCUMENT AT A TIME, BECAUSE THREE ARE VERSIONED SEPARATELY.**
     * This is the question a carrier reviewer actually asks — *did this business
     * agree to the SMS programme terms, and which words were those?* — and a
     * method that answered "yes, they accepted the terms" would be answering a
     * question nobody asked with a fact about a different document. That is the
     * whole reason the table holds three rows rather than one, and a reader that
     * collapsed them would undo it at the last step.
     *
     * ⚠️ **NULL IS AN ANSWER AND IT IS THE IMPORTANT ONE.** A tenant provisioned
     * before this record existed, or through a door that did not record, has no
     * row — and the honest response is to say so on the screen rather than to
     * render a blank where a version should be.
     *
     * Newest wins: a re-acceptance against a later version supersedes the
     * earlier one as the standing position, and the earlier row stays in
     * {@see self::historyFor()} because it is still evidence of what was agreed
     * then.
     */
    public function latestFor(Business $business, LegalDocumentType $type): ?TermsAcceptance
    {
        $this->assertIsTenant((int) $business->id);

        // The `['business_id', 'doc_type']` index this migration ships exists
        // for exactly this read.
        return TermsAcceptance::query()
            ->where('doc_type', $type->value)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Refuse to answer about a business that is not the tenant in context.
     *
     * ⛔ **THIS IS A READ GUARD AND IT MATTERS MORE HERE THAN ON A WRITE.**
     * `terms_acceptances` is FORCE ROW LEVEL SECURITY and carries a global
     * scope, so a call made with tenant B established returns **B's rows** —
     * correctly, quietly, and under a heading naming business A. RLS catches a
     * forgotten filter; it cannot catch a wrong tenant (`CLAUDE.md`), and this
     * record's entire value is that it says which business agreed to what.
     * Getting that wrong on a screen somebody is answering a subpoena from is
     * worse than showing nothing.
     *
     * `BaaRecords::assertIsTenant()`'s shape, one table over.
     *
     * @throws InvalidArgumentException
     */
    private function assertIsTenant(int $businessId): void
    {
        if ($businessId === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That business is not the tenant in context. Terms acceptances are read under the '
            .'acting business\'s own row-level security, so this would answer about one tenant '
            .'while naming another. Wrap the call in Tenancy::actingAs().'
        );
    }
}
