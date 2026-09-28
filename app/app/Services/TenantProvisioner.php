<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WizardStep;
use App\Models\Business;
use App\Models\PublicAudit;
use App\Models\User;
use App\Models\WizardProgress;
use App\Services\Billing\Subscriptions;
use App\Services\Billing\TrialEligibility;
use App\Services\Compliance\TenantClassification;
use App\Services\Industry\PlacesTypeToIndustry;
use App\Services\Legal\BaaRecords;
use App\Services\Pixel\PixelKeys;
use App\Services\Sms\TenantNumbers;
use App\Services\Tenant\LocationProvisioner;
use App\Support\HashedIp;
use App\Support\Tenancy;

/**
 * Turns a newly registered user into a working tenant.
 *
 * `29` §6.2 orders signup as "`POST /register` → tenant auto-provision →
 * wizard", and `29` §11.2 row 1's gate requires that the tenant "auto-provisions
 * end-to-end". Neither was built. Until this class existed, registering produced
 * a `User` and nothing else — and because ResolveTenant resolves the tenant by
 * looking up a Business owned by that user, the account came out the far side
 * with **no tenant at all**, so every scoped query failed closed. The failure was
 * correct and total: a registered person could not use the application.
 *
 * WHY THIS RUNS INSIDE THE REGISTRATION TRANSACTION rather than on the
 * `Registered` event. A listener is the more idiomatic hook and it is the wrong
 * one here, because the two records are not independently useful: a user without
 * a business is not a partially set-up account, it is a permanently broken one,
 * and nothing in the application can repair it because every repair path is
 * itself tenant-scoped. Either both exist or neither does. See CreateNewUser.
 *
 * WHAT STAGE 0 ALREADY BUILT, AND WHAT IT DID NOT. `Business::provision()`
 * exists and is the only supported way to create a business — the mechanism for
 * getting a row past a policy keyed on its own generated id was designed,
 * written and documented in the creating migration. What was missing was a
 * caller: nothing invoked it at registration, so the mechanism sat unused and
 * the gate line went unmet. This class is that caller, and it deliberately adds
 * no new RLS cleverness of its own.
 *
 * The tenant is left established by provision(), which is what signup wants —
 * the WizardProgress write below depends on it, since BelongsToTenant fills
 * `business_id` from Tenancy::idOrFail(). ResolveTenant sets it again from the
 * session on the next request.
 */
final class TenantProvisioner
{
    public function __construct(
        private readonly TenantClassification $classification,
        private readonly PlacesTypeToIndustry $industries,
        private readonly BaaRecords $baaRecords,
        private readonly LocationProvisioner $locations,
        private readonly Subscriptions $subscriptions,
        private readonly TenantNumbers $numbers,
        private readonly TrialEligibility $trials,
        private readonly PixelKeys $pixelKeys,
    ) {}

    /**
     * A fallback business name, for the visitor who arrived without an audit.
     *
     * Deliberately the person's own name rather than "My Business" or an empty
     * string: the wizard's first question is what the business is called, and a
     * placeholder somebody forgets to change ends up on a review invitation.
     * Their own name is at least true, and obviously theirs to correct.
     */
    public static function fallbackName(User $user): string
    {
        return trim($user->name) === '' ? 'My business' : trim($user->name);
    }

    /**
     * Provision the business, its first location, and the wizard.
     *
     * $auditToken is the free audit the visitor ran before signing up, carried
     * through `/start?audit={token}` by the marketing home's call to action. It
     * is optional in every sense — an absent, unknown, expired or pruned token
     * produces a tenant with an empty pre-fill and no error, because signup must
     * never fail on account of a bonus.
     */
    public function provision(User $user, ?string $auditToken = null): Business
    {
        $audit = $this->liveAudit($auditToken);

        // Spelled out as an if rather than `$audit?->name_snapshot ?? …`,
        // which PHPStan correctly calls redundant, and rather than a ternary
        // over an extracted boolean, which would lose the narrowing PHPStan
        // needs to know `$audit->name_snapshot` is non-null in the branch that
        // reads it. The explicit form also says the thing that matters — an
        // audit exists *and* Google gave it a name — rather than leaning on
        // two different null behaviours agreeing.
        //
        // $nameIsVerified TRAVELS TO FeedbackPages::provisionFor() (decision
        // 334, correcting 325). `fallbackName()` copies the *person's own
        // name* into `$name` when there is no verified audit, and this
        // location's name is what FeedbackPages mints the public, permanent
        // feedback-page slug from — so this is the one place that knows
        // whether the name below is a verified business name or a stand-in,
        // and that knowledge has to reach the mint or a personal name ends up
        // on a printed sign.
        if ($audit instanceof PublicAudit && $audit->name_snapshot !== null) {
            $name = $audit->name_snapshot;
            $nameIsVerified = true;
        } else {
            $name = self::fallbackName($user);
            $nameIsVerified = false;
        }

        // ⚠️ THE COLUMN'S FIRST WRITER OUTSIDE A FACTORY. Until this line,
        // `businesses.data_classification` was set only by BusinessFactory
        // states — so the PHI gate of decisions 421–423 was real, tested, and
        // protected nobody: a dental practice signing up came out `pii` and its
        // patients' words went to a third-party model. Decision 272's shape for
        // the eleventh time.
        //
        // ⚠️ NO AUDIT MEANS `Pii`, AND "UNKNOWN FAILS TOWARD PHI" WOULD BE
        // WRONG HERE. A signup with no audit token has no categories at all —
        // the fallbackName() path above — so failing toward Phi would classify
        // **every ordinary tenant** as PHI. That disables AI analysis for the
        // whole product, and it makes routine *downward* reclassification the
        // normal administrative act: the one direction that re-exposes patient
        // text, performed daily, by whoever is clearing a backlog. A
        // classification everybody carries protects nobody, which is the same
        // failure this line exists to fix, inverted. `forCategories([])`
        // therefore answers Pii, and the wizard question is what will catch the
        // practice that arrived without an audit.
        //
        // ⚠️ AND THE SIGNAL IS UNDER-INCLUSIVE — see TenantClassification. A
        // category match is the backstop; the primary signal is a person
        // answering COMP-02's wizard question, which does not exist and needs
        // owner sign-off on its customer-facing wording before it can. Do not
        // read the green tests on this as coverage.
        $classification = $this->classification->forCategories(
            $audit instanceof PublicAudit ? $audit->categories : [],
        );

        $industry = $this->industries->fromCategories($audit instanceof PublicAudit ? $audit->categories : []);

        // Business::provision(), never create(). A business cannot be inserted
        // the ordinary way: its RLS policy is keyed on its own id, so WITH CHECK
        // cannot match an id that does not exist yet, and USING cannot make the
        // new row visible to the RETURNING clause Laravel uses to read that id
        // back. Postgres reports both failures as "new row violates row-level
        // security policy", which points at WITH CHECK and sends you the wrong
        // way entirely.
        //
        // provision() takes the id from the sequence first, so the tenant is
        // known before the row exists and an ordinary insert satisfies both
        // halves. It leaves the tenant established, which is exactly what signup
        // wants — everything below this line is scoped by it.
        // provision() uses forceFill(), so it reaches past Business::$guarded —
        // which is what lets this line set a column no request may. That is the
        // division: the guard refuses request-shaped mass assignment, and this
        // one deliberate writer is named in an ArchitectureTest lint.
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => $name,
            'data_classification' => $classification,
            'industry' => $industry?->value,
        ]);

        // A PHI tenant gets its Business Associate Agreement opened, pending,
        // on the same line it becomes a PHI tenant.
        //
        // ⚠️ `29` §2 rule 24 holds a PHI tenant's form values at `schema_only`
        // "until the BAA is executed", and until this phase that phrase had no
        // state anywhere: `legal_documents` versions the *template* (420) and
        // nothing recorded an execution against a named business. Opening the
        // record here is what gives the condition something to be answered
        // from — a pending row says, truthfully, "this tenant is a covered
        // entity and no agreement is in force yet".
        //
        // ⚠️ IT IS DELIBERATELY `Pending` AND NOT SOMETHING FRIENDLIER. Decision
        // 290's rule: writing an acknowledgement nobody made defeats the only
        // thing the field is for, and here the field is the evidence a
        // regulator would be shown.
        //
        // Only for PHI. An ordinary tenant needs no BAA, and a pending record
        // on every business would make "has a BAA record" meaningless — which
        // is the state that makes the next reader trust it.
        if ($classification->requiresPhiIsolation()) {
            $this->baaRecords->open($business);
        }

        // ⚠️ THE COLUMN'S FIRST WRITER OUTSIDE A FACTORY, AND THE SAME SHAPE AS
        // THE `data_classification` LINE ABOVE. `businesses.pixel_tenant_id` has
        // existed since the Stage 0 schema, `BusinessFactory` fills it, and
        // `plugins.embed_key` cites it as the pattern to copy — and nothing in
        // `app/` ever set it, so every real tenant carried NULL. §11 row 1's
        // "validate `data-k` → tenant" would then have resolved nothing, for
        // everybody, forever, while every factory-built test passed. Decision
        // 272's shape again; PixelKeys records the full argument.
        //
        // ⚠️ MINTED FOR EVERY TENANT, INCLUDING A PHI ONE. The key identifies a
        // business; it does not authorise collection. A covered entity's traffic
        // is refused at the collector by PixelCollector's HIPAA gate and again by
        // the archive (4863), and withholding the key instead would put a third
        // decider on that boundary — in a place whose failure mode is a silent
        // 204 rather than a named refusal.
        //
        // ⚠️ EXISTING TENANTS ARE NOT BACK-FILLED. PixelKeys::ensureFor() is
        // idempotent, so the install screen that hands the snippet out can mint
        // one on demand — `Livewire\Account\PixelInstall` (`/account/tracking`,
        // decision 4979 item 2), which calls this same method as its fallback.
        // A migration writing public identifiers was refused instead, because
        // that is a thing that cannot be undone if the shape is wrong.
        $this->pixelKeys->ensureFor($business);

        // One location, named for the business. `29`'s commercial model includes
        // one location in the base plan, and a business with none is a tenant
        // that cannot be audited, invited from, or reported on — every one of
        // those hangs off a location.
        //
        // ⚠️ THE FIVE ROWS A LOCATION IS HAVE MOVED TO LocationProvisioner, AND
        // THE MOVE IS WHAT MADE A SECOND LOCATION BUYABLE. They were inline here
        // — create, seedDefaults, AutopilotSettings, feedbackPages, widgets — so
        // the only way to make another one was to call all five again from a
        // screen, or to call this method and get a second subscription with it.
        // The reasoning for each of the five went with it; what stays here is why
        // registration makes one at all.
        //
        // $nameIsPersonal: TRUE WHENEVER THE AUDIT DID NOT VERIFY THE NAME, which
        // is unchanged and is the one argument this caller alone can answer —
        // `fallbackName()` copies the signing-up person's own name.
        $this->locations->provisionLocation($name, nameIsPersonal: ! $nameIsVerified);

        // The tenant's own Infobip number, claimed out of the platform pool —
        // dedicated number allocation, one number per tenant, voice and SMS and MMS on the same row
        // because the missed-call text-back has to come from the number the
        // caller just dialled.
        //
        // ⚠️ **`TenantNumbers::assign()` HAD NO CALLER IN `app/` UNTIL THIS
        // LINE**, and it is the shape `CLAUDE.md` lists first: a service with
        // rules, refusals and a full test file that nothing ever invokes. The
        // consequence was not an inert feature but a dead compliance control —
        // with no tenant number, `TenantNumbers::tenantFor()` answers null for
        // every inbound message, so `ComplianceReplies::answerHelp()` could never
        // name a business and **the carrier-mandated HELP reply sent nothing**,
        // on a green suite, for every tenant that has ever existed.
        //
        // ⛔ **IT MAY REFUSE THE WHOLE REGISTRATION.** `claimForTenant()` throws
        // `NumberPoolExhausted` when the pool is in use and empty, which rolls
        // this transaction back exactly the way an integrity failure does. That
        // is the class docblock's own rule applied to one more record: a tenant
        // whose inbound events resolve to nobody is not partially set up, they
        // are permanently and invisibly broken, and no repair path exists because
        // every repair path is tenant-scoped.
        //
        // ⚠️ **AND IT ANSWERS NULL ON A PLATFORM WITH NO POOL AT ALL**, which is
        // every environment until an operator runs `sms:load-number-pool`. That
        // is deliberate and is logged rather than swallowed — see
        // `TenantNumbers::claimForTenant()` for why "no inventory yet" must not
        // be collapsed into "the inventory ran out".
        $this->numbers->claimForTenant($business->id);

        // The billing row, recording exactly what is true at this instant:
        // registered, and Stripe has not been told yet.
        //
        // ⚠️ `subscriptions` HAD NO WRITER EITHER — the twelfth instance of
        // decision 272's shape, and the one with a bill attached. It has had a
        // reader since Stage 0 (`Api\MeController`), which returned null for
        // every tenant that has ever existed, so the endpoint reported "no
        // subscription" to people who were mid-signup.
        //
        // ⚠️ NO STRIPE CALL HAPPENS HERE, AND THAT IS DELIBERATE RATHER THAN
        // MISSING (decisions 685, 687). Slice A wrote `trialing` at this line
        // and said at the write that it meant "registered"; slice B gives that
        // state its own name and moves the trial itself to Checkout, which runs
        // *after* this transaction commits. A network call to a third party
        // inside a database transaction holds the transaction open across the
        // round trip — and a rollback after Stripe answered would destroy our
        // only record of a customer that now exists on their side.
        //
        // Inside the registration transaction with everything else, for the
        // reason at the top of this class: a tenant half-provisioned is not
        // partially set up, it is permanently broken.
        $this->subscriptions->openPendingSignup($business);

        // Where this registration came from, as a keyed hash and never an
        // address — decision 2066's velocity half, in its durable form.
        //
        // ⚠️ TWO CONTROLS, NOT ONE, AND THEY DO DIFFERENT JOBS.
        // `RegistrationRateLimits` refuses a *burst* out of the cache and forgets
        // it an hour later; this row is what still knows, at grant time weeks
        // from now, that nine accounts arrived from one place in one afternoon.
        // A rate limiter alone would have expired long before anything asks.
        //
        // ⚠️ IT REFUSES NOTHING. A shared office, an agency and a carrier-grade
        // NAT all look like this, so the claim only ever contributes to
        // withholding an allowance that a support operator can hand over in one
        // action — see TrialEligibility.
        //
        // ⚠️ AND IT IS INSIDE THE REGISTRATION TRANSACTION WITH EVERYTHING ELSE,
        // on this class's own rule: an account provisioned without its claim is
        // an account the controls cannot see afterwards, and nothing would ever
        // say so.
        $this->trials->recordSignupOrigin(
            $business,
            // `request()` rather than a constructor-held Request: this service is
            // also resolved by console commands and tests, where an injected
            // Request would be whichever one built the container. `HashedIp`
            // answers null when there is no client address, and
            // `recordSignupOrigin()` treats that as "we could not tell" rather
            // than inventing an origin — see its docblock for why that hole is
            // the right one.
            HashedIp::of(request()),
        );

        // forceFill(), not create(): WizardProgress guards current_step and
        // completed (see its docblock) so that SetupFlow::complete() stays
        // the only path that can mark a wizard finished.
        (new WizardProgress)->forceFill([
            'user_id' => $user->id,
            'current_step' => WizardStep::first(),
            'completed' => false,
            'data' => $this->prefill($audit),
        ])->save();

        return $business;
    }

    /**
     * The audit behind a token, if there is still one to find.
     *
     * `live()` matters as much as the token match: decision 192 prunes audits at
     * 90 days, and an expired row must not pre-fill a wizard with findings whose
     * evidence no longer exists.
     */
    private function liveAudit(?string $token): ?PublicAudit
    {
        if ($token === null || $token === '') {
            return null;
        }

        return PublicAudit::query()
            ->live()
            ->where('token', $token)
            ->first();
    }

    /**
     * The audit, copied into the wizard's data bag.
     *
     * COPIED, NEVER REFERENCED — `BUILD-PLAN` §2.5.2 is explicit, and the reason
     * is that `public_audits` prunes on its TTL while `wizard_progress` does not.
     * A foreign key from inside the tenant boundary to a table that deletes rows
     * on a schedule is a dangling reference with a date on it; duplicated text is
     * merely duplicated.
     *
     * THE TOKEN IS PROVENANCE AND NOTHING ELSE. It is stored so that "where did
     * this pre-fill come from?" has an answer sixty days later, and **no code may
     * dereference it** — the moment something looks the token up, every argument
     * above is undone and the dangling reference is back, just spelled
     * differently. `tests/Feature/TenantProvisioningTest.php`'s *"the pre-fill
     * survives the audit being pruned"* asserts the copy stands alone by pruning
     * the audit and checking the wizard is unchanged. ⛔ **THIS CITED a
     * `SliceHPrefillTest` AND NO FILE OF THAT NAME HAS EVER EXISTED —
     * CORRECTED 2026-08-25 (9662).**
     *
     * @return array<string, mixed>
     */
    private function prefill(?PublicAudit $audit): array
    {
        if (! $audit instanceof PublicAudit) {
            return [];
        }

        return [
            'audit' => [
                // Provenance only. Never looked up. See above.
                'token' => $audit->token,
                'name' => $audit->name_snapshot,
                'categories' => $audit->categories,
                'findings' => $audit->findings,
                'score' => $audit->score,
                'captured_at' => $audit->created_at->toIso8601String(),
            ],
        ];
    }
}
