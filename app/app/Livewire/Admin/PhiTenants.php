<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\DataClassification;
use App\Enums\LegalDocumentType;
use App\Models\AuditLogEntry;
use App\Models\BaaRecord;
use App\Models\Business;
use App\Services\AuditService;
use App\Services\Compliance\TenantClassification;
use App\Services\Legal\BaaRecords;
use App\Services\Legal\LegalDocuments as Documents;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;
use Masmerise\Toaster\Toaster;

/**
 * Health-information tenants and their Business Associate Agreements.
 *
 * ⚠️ DELIBERATELY SMALL, AND DELIBERATELY BUILT ANYWAY — decision 410's
 * precedent, applied. `BaaRecords` and `TenantClassification::reclassify()`
 * would otherwise ship with no caller, which is the single most repeated defect
 * in this codebase: `Business::provision()` (272), `autopilot_settings` (377),
 * `feedback_pages`, `review_destinations` and `plugins` (399) were each written,
 * documented and never invoked, and every one of them looked complete. This is
 * a lookup and three actions, not DASH-02.
 *
 * ⚠️ IT DOES NOT LIST PHI TENANTS, AND THAT IS A LIMIT OF THE SCHEMA RATHER
 * THAN OF THIS SCREEN. `businesses` carries two RLS policies and neither one
 * admits platform staff: `tenant_isolation` matches `app.business_id` and
 * `owner_lookup` matches `app.user_id` against `owner_user_id`. So the runtime
 * role — which is the role this application connects as, deliberately — cannot
 * enumerate businesses at all, and a cross-tenant list would need a third
 * policy plus a session variable saying "I am staff". That is a new
 * platform-wide authorization surface on the table the whole isolation gate
 * rests on, and it is not this phase's to invent. Until somebody rules on it,
 * an admin acts on one business at a time, named by its number.
 *
 * ⚠️ THIS IS THEREFORE THE FIRST STAFF SURFACE THAT ACTS AS ANOTHER TENANT, and
 * it is narrow on purpose: it reads a business's name and classification and
 * its BAA record, and it can touch nothing else. Every act runs inside
 * `Tenancy::actingAs()` so the audit entry lands in that tenant's own log rather
 * than in whichever tenant the admin was viewing (decision 419). The gate is
 * `AdminAccess::GATE`, which asks `isPlatformStaff()` and is checked in mount()
 * and again in every action — never a role comparison here.
 *
 * ⚠️ NO SIGNER NAME EVER REACHES A TOAST, AND NEITHER DOES A BUSINESS NAME.
 * Toast text carries no personal data (decision 104), signer names are PII of
 * named individuals, and a business name is not reliably impersonal either —
 * `TenantProvisioner::fallbackName()` copies the owner's own name when nobody
 * ran an audit, which is decision 334's finding one surface over.
 *
 * NOT A DOWNGRADE PATH. The screen raises a tenant to health-information
 * handling and cannot lower one, because `reclassify()` refuses — see its
 * docblock for why the two directions do not cost the same thing.
 */
final class PhiTenants extends Component
{
    /**
     * The business number an admin typed. A string, because it comes from a text
     * input and an unparseable one has to be answerable rather than fatal.
     */
    public string $lookup = '';

    /**
     * The business in view, once one has resolved.
     *
     * ⚠️ `#[Locked]` BECAUSE `lookUp()` IS THE ONLY THING THAT MAY SET IT, AND
     * THE AUDIT ROW IS WRITTEN THERE. Without it this is an ordinary public
     * property: it arrives in the update payload, so anybody who can reach this
     * component could set it to any integer and let `render()` read that
     * business's name, its classification and — once an agreement is executed —
     * the signer's name, title and dates, with `lookUp()` never called and
     * nothing recorded anywhere. That is the exact harm the
     * `business.viewed_by_staff` entry exists to close, walked around rather
     * than through. Locked makes the client-side update a refusal instead, so
     * the audited path is the only path into this property.
     */
    #[Locked]
    public ?int $businessId = null;

    public string $tenantSigner = '';

    public string $tenantTitle = '';

    /**
     * The date the tenant signed, `Y-m-d`. Ours is `now()` — we sign when we
     * record — but theirs is a fact about a piece of paper that was signed
     * before it reached us.
     */
    public string $tenantSignedOn = '';

    /**
     * Why this tenant is being raised to health-information handling.
     */
    public string $raiseReason = '';

    /**
     * Why the agreement ended. Two properties rather than one shared box: bound
     * to the same property, typing in either form would fill the other.
     */
    public string $endReason = '';

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Put a business in view.
     *
     * A miss says so plainly rather than 404ing the screen: the admin has
     * typed a number, and "there is no business 4180" is the answer to that,
     * where a 404 reads as the screen itself being broken.
     *
     * ⚠️ A RESOLVED LOOKUP IS AUDITED IN THE LOOKED-UP TENANT'S OWN LOG. This is
     * the first staff surface in this codebase that reads across the tenant
     * boundary, and what it shows is that business's name, its data
     * classification and — once an agreement is executed — the signer's name,
     * title and dates. Every *write* here already lands in the right tenant's
     * log (419); a read that leaves no trace lets staff walk the business id
     * space with nothing anywhere to say they did, while the branch's own PHI
     * tripwire says "every read of PHI is auditable or the separation proves
     * nothing after the fact".
     *
     * ⚠️ WHAT IT DOES NOT COVER, SAID RATHER THAN IMPLIED. One entry per
     * resolved lookup, not per render: Livewire re-renders on every property
     * update, so auditing `render()` would write a row per keystroke and the log
     * would be unreadable, which is its own kind of unaudited. A miss writes
     * nothing — there is no tenant to file it under, and "no business 4180"
     * discloses nothing about a business that does not exist.
     *
     * ⚠️ AND IT CAN THROW, WHICH IS CORRECT RATHER THAN OVERSIGHT. The audit
     * write is not wrapped and `$this->businessId` is set after it, so an
     * unwritable `audit_log` fails the whole action instead of showing the
     * business. Reordering the two lines would not soften that — a throwing
     * Livewire action commits no property change at all — and catching it would
     * be worse than ugly: it would put a tenant's name, classification and
     * signer details on screen with nothing anywhere recording the read, which
     * is the single thing this entry exists to prevent. Fail closed. An
     * unwritable audit log is an operational failure and reads as one; there is
     * no sentence an admin could act on, and "there is no business 4180" would
     * be a lie about a business that exists.
     */
    public function lookUp(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);

        $id = (int) trim($this->lookup);

        if ($id <= 0) {
            $this->businessId = null;
            Toaster::error('Enter a business number.');

            return;
        }

        $business = $this->resolve($id);

        if (! $business instanceof Business) {
            $this->businessId = null;
            Toaster::error("There is no business {$id}.");

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record('business.viewed_by_staff', $this->actor(), $business),
        );

        $this->businessId = $id;
    }

    /**
     * Raise this tenant to health-information handling.
     *
     * ⚠️ THE REASON IS BOUNDED BECAUSE OF WHERE IT LANDS. `reclassify()` writes
     * it into `audit_log`, which is append-only forever — so an unbounded box
     * lets an operator paste a document into a table nothing can edit
     * afterwards, and 500 characters is enough for the sentence this field
     * exists to hold. The service still refuses an empty one; this refuses it
     * earlier, in the form, where the admin can see which box is wrong.
     *
     * ⚠️ AUTHORIZE BEFORE VALIDATE, HERE AND IN THE OTHER TWO ACTIONS. `act()`
     * checks the gate as well, but `validate()` runs before `act()` is reached —
     * so an ungated caller was answered with a validation error, which tells
     * them the action exists and which fields it wants. Not exploitable, because
     * nothing past the gate runs either way; consistent with `lookUp()`, which
     * authorizes on line one, and one less shape to reason about.
     */
    public function raiseToPhi(TenantClassification $classification): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->validate([
            'raiseReason' => ['required', 'string', 'max:500'],
        ], attributes: [
            'raiseReason' => 'reason',
        ]);

        $this->act(function () use ($classification): string {
            $business = $this->requireBusiness();

            // ⚠️ A TENANT ALREADY RAISED IS REFUSED HERE, BECAUSE THE SERVICE
            // ANSWERS IT WITH SILENCE. `reclassify()` returns early when the
            // classification is unchanged and writes nothing — deliberately,
            // so an append-only compliance log does not record moves that did
            // not happen — and this method then returned "Health information
            // handling recorded" regardless. That is outcome language naming an
            // outcome that did not occur, on the one screen where "did the raise
            // take?" has a legal answer.
            //
            // The form is not rendered for a PHI tenant, which is not a guard:
            // a Livewire action is callable whatever rendered it (decision 391),
            // and that is precisely the argument that put the refusals into
            // `BaaRecords::open()` and `revoke()`. Two of the three actions were
            // hardened against un-rendered invocation and this one was left to
            // succeed quietly.
            if ($business->data_classification === DataClassification::Phi) {
                throw new InvalidArgumentException(
                    'This tenant already handles health information.'
                );
            }

            $classification->reclassify(
                $business,
                DataClassification::Phi,
                $this->actor(),
                $this->raiseReason,
            );

            $this->raiseReason = '';

            // Outcome language, and the verb survives the flow (`22`, `29`
            // §5.7). No business name — see the class docblock.
            return 'Health information handling recorded';
        });
    }

    /**
     * Record that both sides signed the published agreement.
     */
    public function recordExecution(BaaRecords $records, Documents $documents): void
    {
        // Before validate(), for the reason raiseToPhi() gives.
        $this->authorize(AdminAccess::GATE);

        $this->validate([
            'tenantSigner' => ['required', 'string', 'max:255'],
            'tenantTitle' => ['required', 'string', 'max:255'],
            // `before_or_equal:today` because a signature dated next month is
            // not a signature. `date` alone would accept it, and the CHECK
            // constraint has nothing to say about *which* date.
            'tenantSignedOn' => ['required', 'date', 'before_or_equal:today'],
        ], attributes: [
            'tenantSigner' => 'signer',
            'tenantTitle' => 'signer\'s title',
            'tenantSignedOn' => 'date they signed',
        ]);

        // Parsed outside act(), and the placement is deliberate rather than
        // stylistic: `CarbonImmutable::parse()` throws Carbon's
        // InvalidFormatException, which extends InvalidArgumentException, so a
        // parse failure inside the try below would be shown to an admin as
        // though a compliance rule had refused their input. The `date` rule
        // above is what makes this unreachable; keeping it out of the try is
        // what keeps that true if the rule ever changes.
        $signedOn = CarbonImmutable::parse($this->tenantSignedOn)->startOfDay();

        $this->act(function () use ($records, $documents, $signedOn): string {
            $business = $this->requireBusiness();

            // ⚠️ THE VERSION IS RESOLVED HERE, NEVER SENT BY THE CLIENT. A
            // Livewire property arrives in the update payload and nothing about
            // it is trustworthy, and the one thing this action must not get
            // wrong is *which words* the tenant is recorded as having signed.
            //
            // ⚠️ AND IT IS RESOLVED FROM THE SIGNING DATE, NOT FROM TODAY.
            // `current()` is the newest published version, which is a different
            // question: publish v1.1 on Monday, record a paper signature dated
            // the week before, and `current()` names words that did not exist
            // when they signed. `recordExecution()` re-checks type, publication,
            // placeholder state and this same date regardless, so the write path
            // fails closed independently of this one (314–316).
            $version = $documents->currentAsOf(LegalDocumentType::Baa, $signedOn)
                ?? throw new InvalidArgumentException(
                    'No version of the Business Associate Agreement was published on or before '
                    .$signedOn->format('j F Y').'. An execution names the exact words the tenant '
                    .'signed: check the date, and publish a final version if none exists yet.'
                );

            // ⚠️ ASKED BEFORE open(), WHICH IS THE WHOLE REASON THIS IS TWO
            // CALLS. `open()` writes a pending row and a `baa.opened` audit
            // entry, and PHP evaluates arguments before the call they belong to
            // — so passing `$records->open($business)` straight into
            // `recordExecution()` left both behind on every attempt the version
            // check then refused. A refused attempt must leave nothing.
            $records->refuseUnusableVersion($version, $signedOn);

            $records->recordExecution(
                $records->open($business),
                $version,
                $this->tenantSigner,
                $this->tenantTitle,
                $signedOn,
                $this->goaiezSigner(),
                $this->actor(),
            );

            $this->tenantSigner = '';
            $this->tenantTitle = '';
            $this->tenantSignedOn = '';

            return 'Agreement recorded as signed';
        });
    }

    /**
     * End the agreement.
     *
     * Here rather than service-only for the reason the whole screen exists: a
     * method nobody can call is the defect this codebase has recorded eleven
     * times.
     *
     * ⚠️ THE REASON IS BOUNDED FOR `raiseToPhi()`'s REASON. It lands in
     * `baa_records.revoke_reason` *and* in the append-only audit entry, so two
     * copies of whatever an operator types — see `BaaRecords::revoke()` on what
     * that means for the personal data nobody scrubs out of it.
     */
    public function endAgreement(BaaRecords $records): void
    {
        // Before validate(), for the reason raiseToPhi() gives.
        $this->authorize(AdminAccess::GATE);

        $this->validate([
            'endReason' => ['required', 'string', 'max:500'],
        ], attributes: [
            'endReason' => 'reason',
        ]);

        $this->act(function () use ($records): string {
            $business = $this->requireBusiness();

            $record = $records->forBusiness($business) ?? throw new InvalidArgumentException(
                'There is no agreement to end for this business.'
            );

            $records->revoke($record, $this->endReason, $this->actor());

            $this->endReason = '';

            return 'Agreement ended';
        });
    }

    public function render(BaaRecords $records, Documents $documents): View
    {
        $this->authorize(AdminAccess::GATE);

        $business = $this->businessId === null ? null : $this->viewing(
            fn (): ?Business => Business::query()->find($this->businessId),
        );

        return view('livewire.admin.phi-tenants', [
            'business' => $business,
            // Through the service, never a query of its own: `BaaRecords` is the
            // only reader and writer of that table, and an ArchitectureTest lint
            // says so.
            'record' => $business instanceof Business ? $this->viewing(
                fn (): ?BaaRecord => $records->forBusiness($business),
            ) : null,
            // ⚠️ THE SERVICE ANSWERS "IS IT IN FORCE", NOT THE VIEW. The row is
            // rendered for its evidence — who signed, when, against which
            // version — but whether rule 24's condition is met is one question
            // with one answer, and a template reading `status === 'executed'`
            // would be a second definition of it. `isExecutedFor()` fails closed
            // on all three of absent, pending and ended.
            'inForce' => $business instanceof Business && $this->viewing(
                fn (): bool => $records->isExecutedFor($business),
            ),
            'template' => $documents->current(LegalDocumentType::Baa),
        ]);
    }

    /**
     * Run one action as the business in view, and show a refusal rather than
     * swallowing it.
     *
     * ⚠️ ONE CATCH, AND IT IS BROADER THAN THE TWO CASES IT IS FOR. The two
     * deliberate ones are `InvalidArgumentException` — a service refusing an
     * input, whose message explains a rule the admin can act on — and
     * `LogicException`, which is `reclassify()` refusing to lower a PHI tenant,
     * a rule nobody may act on from here at all. Showing both beats a generic
     * failure notice, which is how somebody concludes the rule is a bug
     * (`ReviewQueue` and `LegalDocuments`' stated reasoning).
     *
     * ⚠️ BUT PHP DRAWS NO DISTINCTION BETWEEN THEM: `InvalidArgumentException`
     * **extends** `LogicException`, so naming both would be one catch written
     * twice. This clause therefore also swallows `DomainException`,
     * `LengthException` and Carbon's `InvalidFormatException` — a programming
     * error presented to an admin as a compliance refusal, in a screen where
     * "the rule refused you" and "this is broken" have to be told apart. The one
     * live source of that was `CarbonImmutable::parse()`, and it is parsed
     * outside this method for exactly that reason. Anything new that can throw
     * a third `LogicException` subclass in here needs the same treatment or its
     * own catch above this one.
     *
     * @param  callable(): string  $action
     */
    private function act(callable $action): void
    {
        $this->authorize(AdminAccess::GATE);

        try {
            $outcome = $this->viewing($action);
        } catch (LogicException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        Toaster::success($outcome);
    }

    /**
     * Run a callback as the business in view.
     *
     * ⚠️ THIS IS THE CROSS-TENANT STEP, IN ONE PLACE. `Tenancy::actingAs()`
     * restores the previous tenant in a finally block, so a throwing callback
     * cannot leave the admin's session pointed at somebody else's business. Both
     * the reads in render() and every write go through here, so there is no
     * second spelling of it to keep in step.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function viewing(callable $callback): mixed
    {
        $id = $this->businessId ?? throw new InvalidArgumentException(
            'Look up a business first.'
        );

        return Tenancy::actingAs($id, $callback);
    }

    /**
     * The business in view, re-read under scope on every action.
     *
     * ⚠️ `$businessId` IS SERVER-SET, AND `#[Locked]` IS WHAT MAKES THAT TRUE.
     * This docblock previously said the opposite — that the property is
     * attacker-supplied and only the gate makes it safe — which was accurate of
     * an unlocked public property and is now a claim in the wrong direction: a
     * reader who believes it will assume the audit row on `lookUp()` can be
     * walked around, and the next "simplification" is to drop the attribute
     * because "the gate covers it anyway". It does not. See the property.
     *
     * ⚠️ THE GATE AND `Tenancy::actingAs()` ARE STILL DOING THEIR OWN JOBS.
     * Locked says only that this application put the id here; it says nothing
     * about whether the person may act on it, which is `AdminAccess::GATE`,
     * checked in mount() and again first in every action. And every read and
     * write still runs inside `Tenancy::actingAs()`, so RLS and the global scope
     * bound the id whatever it turns out to be — the three are layers, not
     * substitutes (314–316).
     *
     * ⚠️ RE-READ RATHER THAN HELD IN A PROPERTY, separately from all of that: an
     * action would otherwise run against a classification that changed in
     * another tab, and the one decision this screen makes reads it.
     */
    private function requireBusiness(): Business
    {
        return Business::query()->find($this->businessId) ?? throw new InvalidArgumentException(
            'That business no longer exists.'
        );
    }

    /**
     * The business a typed number resolves to, or null.
     *
     * Its own tenant context, because `businesses` is FORCE ROW LEVEL SECURITY
     * and a lookup with the admin's own tenant established would answer "no"
     * for every business but their own.
     *
     * Returns the model rather than a boolean so the audit entry for the lookup
     * can name what was read — one query either way.
     */
    private function resolve(int $id): ?Business
    {
        return Tenancy::actingAs(
            $id,
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );
    }

    /**
     * Who is recording this, for the audit entry.
     *
     * An actor label, matching `ReviewQueue` and `LegalDocuments`: automation is
     * a first-class actor in this log, so a user foreign key would have nothing
     * to point at for most entries.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }

    /**
     * Who signed for us.
     *
     * ⚠️ THE AUTHENTICATED USER'S NAME, NOT A TYPED FIELD. A signature block
     * somebody fills in for themselves records whoever they say signed; this
     * records who was logged in when it was recorded, which is the fact this
     * screen actually has. It is not a wet signature and does not pretend to be
     * — the paper is the agreement, and this row is our record of it.
     */
    private function goaiezSigner(): string
    {
        $user = auth()->user();

        return $user === null ? 'admin' : $user->name;
    }
}
