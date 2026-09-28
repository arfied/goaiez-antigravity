<?php

declare(strict_types=1);

use App\Enums\BaaStatus;
use App\Enums\DataClassification;
use App\Enums\LegalDocumentType;
use App\Livewire\Admin\PhiTenants;
use App\Models\AuditLogEntry;
use App\Models\BaaRecord;
use App\Models\Business;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\Compliance\TenantClassification;
use App\Services\Legal\BaaRecords;
use App\Services\Legal\LegalDocuments as Documents;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The health-information admin screen
|--------------------------------------------------------------------------
|
| It exists so `BaaRecords` and `TenantClassification::reclassify()` have a
| caller — a service written, documented and never invoked is the single most
| repeated defect in this codebase (272, 377, 399, and eight more), and every
| instance looked complete. Decision 410's precedent.
|
| ⚠️ THE SCREEN ACTS AS ANOTHER TENANT, WHICH NO OTHER STAFF SURFACE HERE DOES.
| That is what the last two tests in this file are about: the act has to land in
| the acted-on tenant's own audit log, and the admin's own tenant has to be
| restored afterwards.
|
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);

    $this->admin = User::factory()->create(['name' => 'Platform Staff']);

    Tenancy::forgetAll();

    $this->business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Bright Smile Dental',
    ]);

    Tenancy::forgetAll();
});

/**
 * A published, final BAA template — the only kind that may be executed against.
 *
 * `$publishedAt` is set at INSERT rather than updated afterwards: a published
 * row is frozen by a trigger and by a model hook alike (417–420), so there is no
 * other way to build a version history in a test.
 */
function publishedBaaForAdmin(string $version = '1.0', ?CarbonInterface $publishedAt = null): LegalDocument
{
    return LegalDocument::factory()
        ->ofType(LegalDocumentType::Baa)
        ->published()
        ->create([
            'version' => $version,
            'is_placeholder' => false,
            'published_at' => $publishedAt ?? now(),
        ]);
}

/**
 * A predicate for `assertDispatched('toaster:received', …)` — the toast payload
 * as the browser receives it.
 *
 * ⚠️ THE PAYLOAD, NOT THE FACADE. `masmerise/livewire-toaster`'s own
 * `Toaster::fake()` collector is drained by its Livewire relay on dehydrate, so
 * a test holding the fake finds it empty by the time it asserts. What survives
 * is the `toaster:received` dispatch the relay pushes onto the component's
 * effects, carrying `message` and `type` — which is also the thing that reaches
 * a person, rather than an intermediate this codebase happens to use.
 *
 * ⚠️ AND THE TYPE IS HALF THE ASSERTION. `Toaster::success()` and
 * `Toaster::error()` differ only in that field, so a check on the message alone
 * cannot tell a refusal from a confirmation — which is the exact confusion two
 * of the tests below exist to pin.
 *
 * Substring rather than equality on the message, because these sentences are
 * the service's and pinning them whole would make every wording change a test
 * change; the phrase asked for is the part carrying the meaning.
 *
 * @return callable(string, array<string, mixed>): bool
 */
test('an admin raises a business to health information and its agreement opens', function (): void {
    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('raiseReason', 'The owner confirmed they are a dental practice.')
        ->call('raiseToPhi')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(Business::query()->whereKey($this->business->id)->sole()->data_classification)
            ->toBe(DataClassification::Phi)
            ->and(BaaRecord::query()->sole()->status)->toBe(BaaStatus::Pending);
    });
});

test('an admin records the signed agreement', function (): void {
    // Published before the signature is dated, which is the ordinary order of
    // events — counsel publishes, the tenant signs, staff record it. A version
    // published after the signing date is refused, and that is its own test.
    publishedBaaForAdmin('1.0', now()->subMonth());

    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('tenantSigner', 'A. Signer')
        ->set('tenantTitle', 'Practice Manager')
        ->set('tenantSignedOn', now()->subDay()->toDateString())
        ->call('recordExecution')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $record = BaaRecord::query()->sole();

        expect($record->status)->toBe(BaaStatus::Executed)
            ->and($record->tenant_signer_name)->toBe('A. Signer')
            // We sign by recording it, under the name of whoever was logged in —
            // not a field somebody fills in about themselves.
            ->and($record->goaiez_signer)->toBe('Platform Staff');
    });
});

test('an agreement in force shows its evidence and no second execution form', function (): void {
    // ⚠️ THIS EXISTS BECAUSE ITS ABSENCE WAS FOUND BY MUTATION, NOT BY REVIEW.
    // The screen asks `BaaRecords::isExecutedFor()` whether rule 24's condition
    // is met, rather than letting the template read `status === 'executed'` and
    // become a second definition of "in force". Forcing that answer to `false`
    // left all twelve tests in this file green — decision 411's shape exactly,
    // inside the phase that quotes it. This is the assertion that makes the
    // wiring load-bearing.
    //
    // ⚠️ THE SIGNER'S NAME IS ON SCREEN HERE ON PURPOSE. It is PII, it is behind
    // the platform-staff gate, and this panel is the one place it appears — it
    // is never in a toast, a URL or a log.
    publishedBaaForAdmin('1.0', now()->subMonth());

    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('tenantSigner', 'A. Signer')
        ->set('tenantTitle', 'Practice Manager')
        ->set('tenantSignedOn', now()->subDay()->toDateString())
        ->call('recordExecution')
        ->assertSee('Signed for the business by A. Signer')
        ->assertSee('End this agreement')
        ->assertDontSee('Record the signed agreement');
});

test('an ended agreement is not shown as one in force', function (): void {
    // The other half: `isExecutedFor()` fails closed on a revoked record, so the
    // evidence panel and the "end this agreement" control both go away. Without
    // this, a mutation reading `status !== 'pending'` would keep the test above
    // green.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->business->forceFill(['data_classification' => DataClassification::Phi])->save();

        BaaRecord::factory()->revoked()->create();
    });

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Ended')
        ->assertDontSee('End this agreement')
        ->assertDontSee('Signed for the business by');
});

test('the screen never offers to execute against an unpublished template', function (): void {
    // ⚠️ `29` §12.2 PUTS HEALTHCARE-COUNSEL REVIEW BEFORE THE FIRST PHI TENANT.
    // The service refuses this execution too; the screen simply does not offer
    // it, because a button that exists and then explains why it cannot work is
    // how somebody concludes the rule is a bug.
    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('No final version of the Business Associate Agreement is')
        ->assertDontSee('Record the signed agreement');
});

test('a placeholder version is not offered either, and would be refused if it were', function (): void {
    // A published *placeholder* satisfies "published" and is still filler text —
    // `39` seeds every legal draft that way, so this is the ordinary state of
    // the document rather than an edge case.
    LegalDocument::factory()
        ->ofType(LegalDocumentType::Baa)
        ->published()
        ->create(['version' => '0.9', 'is_placeholder' => true]);

    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertDontSee('Record the signed agreement');
});

test('the screen offers no way to lower a tenant out of health information', function (): void {
    // The refusal lives in `reclassify()`; this asserts the screen agrees with
    // it rather than offering a control the service will reject.
    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertDontSee('Move to health information');
});

test('a refusal from the service is shown rather than swallowed', function (): void {
    // The service's messages explain a rule; a generic failure notice would turn
    // "this would lock the tenant out" into a mystery. `ReviewQueue` and
    // `LegalDocuments`' stated reasoning.
    //
    // ⚠️ THIS TEST WAS NAMED FOR A THING IT DID NOT ASSERT. It checked only that
    // the revocation had not happened — which is `revoke()`'s refusal, not the
    // screen showing it — so deleting `Toaster::error($e->getMessage())` from
    // `act()` left it green, and "shown rather than swallowed" would have been
    // exactly swallowed. Decision 411's shape, in the assertion written to
    // prevent it.
    //
    // ⚠️ MUTATION: delete `Toaster::error($e->getMessage());` from
    // PhiTenants::act(). This reddens on the dispatch.
    //
    // ⚠️ THE REFUSAL DRIVEN HERE HAS TO BE ONE THE FORM CANNOT CATCH FIRST. An
    // empty reason used to be it, and `endReason` is now `required` at the
    // screen — Laravel trims a string for that rule, so whitespace never reaches
    // the service and the test would have been asserting the validator. Ending
    // an agreement that was never signed is a service rule with no form
    // equivalent, and the one whose cost is permanent.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->business->forceFill(['data_classification' => DataClassification::Phi])->save();

        BaaRecord::factory()->create();
    });

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('endReason', 'Opened by mistake.')
        ->call('endAgreement')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('error', 'lock this tenant out permanently'),
        );

    Tenancy::actingAs($this->business->id, function (): void {
        expect(BaaRecord::query()->sole()->status)->toBe(BaaStatus::Pending)
            ->and(AuditLogEntry::query()->where('action', 'baa.revoked')->count())->toBe(0);
    });
});

test('a reason is required on the screen, and bounded before it reaches an append-only table', function (): void {
    // Both reasons land in `audit_log`, which nothing edits afterwards, and the
    // ending one lands in `baa_records.revoke_reason` as well. The service
    // refuses an empty reason; the screen refuses it earlier, where the admin
    // can see which box is wrong, and bounds the length that reaches the table
    // at all — `tenantSigner` and `tenantTitle` had `max:255` and these had
    // nothing.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->business->forceFill(['data_classification' => DataClassification::Phi])->save();

        BaaRecord::factory()->executed()->create();
    });

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('endReason', '   ')
        ->call('endAgreement')
        ->assertHasErrors(['endReason' => 'required'])
        ->set('endReason', str_repeat('a', 501))
        ->call('endAgreement')
        ->assertHasErrors(['endReason' => 'max']);

    // The raise form's reason is bounded the same way, on the same screen.
    $ordinary = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Corner Cafe',
    ]);

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $ordinary->id)
        ->call('lookUp')
        ->set('raiseReason', '')
        ->call('raiseToPhi')
        ->assertHasErrors(['raiseReason' => 'required'])
        ->set('raiseReason', str_repeat('a', 501))
        ->call('raiseToPhi')
        ->assertHasErrors(['raiseReason' => 'max']);

    Tenancy::actingAs($ordinary->id, function () use ($ordinary): void {
        expect(Business::query()->whereKey($ordinary->id)->sole()->data_classification)
            ->toBe(DataClassification::Pii);
    });
});

test('an admin ends an agreement, with the reason recorded', function (): void {
    Tenancy::actingAs($this->business->id, function (): void {
        $this->business->forceFill(['data_classification' => DataClassification::Phi])->save();

        BaaRecord::factory()->executed()->create();
    });

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('endReason', 'The practice closed.')
        ->call('endAgreement')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(BaaRecord::query()->sole()->status)->toBe(BaaStatus::Revoked);
    });
});

test('the signed date is required, must be a date, and cannot be in the future', function (): void {
    // A signature dated next month is not a signature, and the CHECK constraint
    // has nothing to say about *which* date — it only requires that one exists.
    publishedBaaForAdmin();

    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('tenantSigner', 'A. Signer')
        ->set('tenantTitle', 'Practice Manager')
        ->set('tenantSignedOn', now()->addMonth()->toDateString())
        ->call('recordExecution')
        ->assertHasErrors('tenantSignedOn');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(BaaRecord::query()->count())->toBe(0);
    });
});

test('a business number that resolves to nothing clears the one in view', function (): void {
    // ⚠️ THIS TEST COULD NOT FAIL AS FIRST WRITTEN. It looked up a missing id
    // and asserted `businessId` was null — which is the property's *initial*
    // value, so deleting the `$this->businessId = null;` line it exists to pin
    // left it green. Decision 411's shape, inside the branch that cites it.
    // Resolving a real business first is what makes the clearing observable, and
    // it is the case that matters: an admin looking at business 12 and then
    // mistyping must not act on 12.
    //
    // ⚠️ MUTATION: delete the two `$this->businessId = null;` lines from
    // PhiTenants::lookUp(). This reddens on both halves.
    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', '999999')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null)
        // The other miss branch: not a number at all. `(int) 'abc'` is 0, which
        // would otherwise be looked up as business 0.
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', 'not a number')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null);

    // ⚠️ WHAT THIS SECOND HALF DOES *NOT* PIN, verified by mutation rather than
    // assumed. Deleting the `$id <= 0` early return from lookUp() leaves this
    // green: `(int) 'not a number'` is 0, `whereKey(0)` matches nothing, and the
    // miss branch below it clears `businessId` for the same reason. The only
    // thing that branch changes is the sentence the admin reads — "Enter a
    // business number" rather than "There is no business 0" — and toasts are
    // deliberately not the audit record (decision 104), so nothing here asserts
    // one. It is a wording choice, not a boundary, and saying so is cheaper than
    // a test that looks like it guards one.
});

test('a resolved lookup is recorded in the looked-up tenant own log', function (): void {
    // ⚠️ THE FIRST STAFF SURFACE HERE THAT READS ACROSS THE TENANT BOUNDARY.
    // Every write already lands in the right tenant's log (419); this is the
    // read half, and without it staff can walk the business id space — seeing
    // each tenant's name, its data classification, and once an agreement is
    // executed the signer's name, title and dates — with nothing anywhere
    // recording that they did. The branch's own PHI tripwire owes-list says
    // "every read of PHI is auditable or the separation proves nothing after
    // the fact", and this screen shipped in the same commit.
    //
    // ⚠️ MUTATION: delete the `$audit->record('business.viewed_by_staff', …)`
    // call from PhiTenants::lookUp(). This reddens.
    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        expect($entry->actor)->toBe('user:'.$this->admin->id)
            ->and($entry->entity_type)->toBe(Business::class)
            ->and($entry->entity_id)->toBe($this->business->id);
    });
});

test('a lookup that resolves to nothing records nothing', function (): void {
    // There is no tenant to file it under, and "there is no business 999999"
    // discloses nothing about a business that does not exist. Without this, the
    // test above passes against a recorder that writes on every keystroke.
    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', '999999')
        ->call('lookUp');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(0);
    });
});

test('there is no agreement to end when none was ever opened', function (): void {
    // The screen renders no "end" control in this state, so this is the action
    // reached by calling it anyway — which a Livewire action always can be,
    // because nothing gates one by what rendered it (decision 391).
    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('endReason', 'The practice closed.')
        ->call('endAgreement')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(BaaRecord::query()->count())->toBe(0);
    });
});

test('the version recorded is the one that was published when they signed', function (): void {
    // ⚠️ THE SCREEN RESOLVES THE VERSION ITSELF — a client-sent one is the
    // single thing this action must not accept — and it resolved `current()`,
    // the newest published version, while validating the signing date as
    // `before_or_equal:today` on purpose, because a signature is a fact about a
    // piece of paper signed before it reached us. So counsel publishing v1.1 on
    // Monday and staff recording a signature dated the week before produced a
    // row asserting a covered entity agreed to words nobody had written yet.
    //
    // Harmless while exactly one non-placeholder version has ever been
    // published, which is why every test passed. Version history exists because
    // that state ends.
    //
    // ⚠️ MUTATION: change `currentAsOf(LegalDocumentType::Baa, $signedOn)` back
    // to `current(LegalDocumentType::Baa)` in PhiTenants::recordExecution().
    // This reddens on the count — and *how* it reddens is the point. Under the
    // mutation the screen resolves v1.1, and `recordExecution()` refuses it
    // independently, so nothing is recorded at all rather than the wrong words
    // being recorded. That is the two layers doing different jobs (314–316):
    // the resolver names the right version, and the service is what stops the
    // wrong one landing when something else names it.
    $older = publishedBaaForAdmin('1.0', now()->subDays(30));
    $newer = publishedBaaForAdmin('1.1', now()->subDays(2));

    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('tenantSigner', 'A. Signer')
        ->set('tenantTitle', 'Practice Manager')
        ->set('tenantSignedOn', now()->subDays(10)->toDateString())
        ->call('recordExecution')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function () use ($older, $newer): void {
        expect(BaaRecord::query()->count())->toBe(1, 'the execution was refused entirely');

        $record = BaaRecord::query()->sole();

        expect($record->legal_document_id)->toBe($older->id)
            ->and($record->legal_document_id)->not->toBe($newer->id);
    });
});

test('an execution the version check refuses leaves no record behind', function (): void {
    // ⚠️ PHP EVALUATES ARGUMENTS BEFORE THE CALL THEY BELONG TO. The screen
    // passed `$records->open($business)` straight into `recordExecution()`, so
    // a refusal from the version check still left a pending row and a
    // `baa.opened` audit entry — an agreement that appears to exist because
    // somebody typed a date wrong.
    //
    // ⚠️ MUTATION: delete the `$records->refuseUnusableVersion(...)` line from
    // PhiTenants::recordExecution(). This reddens on both counts.
    //
    // ⚠️ THE REFUSAL DRIVEN HERE HAS TO BE ONE THE *RESOLVER* CANNOT MAKE
    // FIRST, and the first draft of this test got that wrong: a signature dated
    // before every published version makes `currentAsOf()` return null, so the
    // action threw before `open()` was ever reached and the mutation stayed
    // green. A published placeholder resolves perfectly and is refused a step
    // later — `39` seeds every legal draft that way, so it is the ordinary
    // state of the document rather than a contrivance.
    LegalDocument::factory()
        ->ofType(LegalDocumentType::Baa)
        ->published()
        ->create([
            'version' => '0.9',
            'is_placeholder' => true,
            'published_at' => now()->subDays(30),
        ]);

    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('tenantSigner', 'A. Signer')
        ->set('tenantTitle', 'Practice Manager')
        // A date the form accepts and the resolver answers: the placeholder was
        // published before it.
        ->set('tenantSignedOn', now()->subDays(10)->toDateString())
        ->call('recordExecution')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(BaaRecord::query()->count())->toBe(0)
            ->and(AuditLogEntry::query()->where('action', 'baa.opened')->count())->toBe(0);
    });
});

test('the audit entry lands in the acted-on tenant own log', function (): void {
    // ⚠️ THE POINT OF `Tenancy::actingAs()` IN THIS SCREEN. `AuditService::
    // record()` opens with `Tenancy::idOrFail()` (decision 419), so an admin
    // acting on a business while their own tenant is established would file the
    // entry under their own business — burying the record of another tenant's
    // agreement in the wrong log, where nobody investigating that tenant would
    // ever look.
    //
    // ⚠️ MUTATION: replace `Tenancy::actingAs($id, $callback)` in
    // PhiTenants::viewing() with a bare `$callback()`. Every write then fails
    // closed instead — which is the second half of why this test is here: the
    // screen cannot silently write into the wrong tenant, because RLS refuses
    // it.
    $other = Business::provision([
        'owner_user_id' => $this->admin->id,
        'name' => 'The admin own business',
    ]);

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('raiseReason', 'Confirmed by the owner.')
        ->call('raiseToPhi')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.reclassified')->count())->toBe(1);
    });

    Tenancy::actingAs($other->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.reclassified')->count())->toBe(0);
    });
});

test('a refused reason is shown on the screen, not only in the error bag', function (): void {
    // ⚠️ THE VALIDATION ADDED FOR THE REASONS WAS INVISIBLE, WHICH MADE IT A
    // REGRESSION RATHER THAN A GUARD. Before it, an empty reason reached the
    // service, was refused, and `act()` showed the refusal as a toast. After it,
    // `validate()` threw first and the message landed in an error bag no part of
    // this screen renders — so "End this agreement" did nothing at all, silently,
    // and an admin had every reason to believe the agreement had ended.
    //
    // ⚠️ THE ASSERTION HAS TO BE ON THE RENDERED RESPONSE. `assertHasErrors`
    // checks the bag, which is precisely the thing that was full while the
    // screen was blank — the test above it was green over an invisible failure
    // for exactly that reason.
    //
    // ⚠️ MUTATION: delete the `@error('endReason')` block from the blade. This
    // reddens on the first assertSee while `assertHasErrors` stays green, which
    // is the finding in miniature. The same for `@error('raiseReason')` and the
    // second half.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->business->forceFill(['data_classification' => DataClassification::Phi])->save();

        BaaRecord::factory()->executed()->create();
    });

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('endReason', '   ')
        ->call('endAgreement')
        ->assertHasErrors(['endReason' => 'required'])
        ->assertSee('The reason field is required.');

    // The raise form's reason, on the same screen and rendered by a different
    // branch of the same template.
    $ordinary = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Corner Cafe',
    ]);

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $ordinary->id)
        ->call('lookUp')
        ->set('raiseReason', '')
        ->call('raiseToPhi')
        ->assertHasErrors(['raiseReason' => 'required'])
        ->assertSee('The reason field is required.');
});

test('the business in view cannot be set from the client', function (): void {
    // ⚠️ WITHOUT `#[Locked]` THE READ AUDIT DOES NOT HOLD. `businessId` is a
    // public Livewire property, so it arrives in the update payload: anybody who
    // can reach this component could set it to any integer and let `render()`
    // read that business's name, its classification and — once an agreement is
    // executed — the signer's name, title and dates, with `lookUp()` never
    // called and its `business.viewed_by_staff` entry never written. Authorized
    // staff, so not a tenancy breach; it is the control the previous wave added
    // failing to hold, one property over from where it was added.
    //
    // ⚠️ MUTATION: delete the `#[Locked]` attribute from
    // PhiTenants::$businessId. This reddens — nothing throws, and the audit
    // count below goes to zero *while the business is in view*, which is the
    // harm stated.
    $component = Livewire::actingAs($this->admin)->test(PhiTenants::class);

    expect(fn (): mixed => $component->set('businessId', $this->business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())
            ->toBe(0, 'a business was put in view with nothing recording the read');
    });
});

test('raising a tenant that already handles health information is refused, not reported as done', function (): void {
    // ⚠️ `reclassify()` ANSWERS THIS WITH SILENCE, DELIBERATELY: an unchanged
    // classification writes nothing, so an append-only compliance log does not
    // record moves that did not happen. The screen then toasted "Health
    // information handling recorded" regardless — outcome language naming an
    // outcome that did not occur, on the one screen where "did the raise take?"
    // has a legal answer.
    //
    // The form is not rendered for a PHI tenant. That is not a guard: a Livewire
    // action is callable whatever rendered it (decision 391), which is the exact
    // argument that put the refusals into `BaaRecords::open()` and `revoke()` —
    // two of the three actions were hardened against un-rendered invocation and
    // this one was left to succeed quietly.
    //
    // ⚠️ MUTATION: delete the `data_classification === Phi` refusal from
    // PhiTenants::raiseToPhi(). This reddens on the type: the same call then
    // dispatches a `success` toast reading "Health information handling
    // recorded".
    Tenancy::actingAs($this->business->id, fn () => $this->business
        ->forceFill(['data_classification' => DataClassification::Phi])->save());

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->set('raiseReason', 'Double-clicked.')
        ->call('raiseToPhi')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('error', 'already handles health information'),
        );

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.reclassified')->count())->toBe(0);
    });
});

test('ending an agreement warns that it cannot be undone, before the button', function (): void {
    // ⚠️ THE MOST TERMINAL CONTROL ON THE SCREEN READ AS THE LEAST. The raise
    // form warns and this one did not, while every exit behind it is closed:
    // `revoke()` refuses a second ending, `recordExecution()` refuses anything
    // but a pending record, re-execution after revocation is not built, and
    // `reclassify()` will not lower a tenant out of health information. So the
    // tenant is left a covered entity with no agreement in force and no path in
    // this application back to one.
    //
    // ⚠️ BEFORE THE BUTTON, WHICH IS WHY THIS ASSERTS POSITION RATHER THAN
    // PRESENCE. A consequence explained underneath the control that causes it is
    // read after the decision, if at all. ⚠️ MUTATION: move the paragraph below
    // the `<form>`, or delete it. Both redden.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->business->forceFill(['data_classification' => DataClassification::Phi])->save();

        BaaRecord::factory()->executed()->create();
    });

    $html = Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->html();

    $warning = strpos($html, 'This cannot be undone here. A tenant cannot be moved back');
    // "End agreement" is the button; the heading reads "End this agreement", so
    // this substring appears exactly once.
    $button = strpos($html, 'End agreement');

    expect($warning)->toBeInt('the ending form carries no irreversibility warning')
        ->and($button)->toBeInt()
        ->and($warning)->toBeLessThan($button);
});

test('the admin gate is checked before the form is validated', function (): void {
    // ⚠️ CALLED ON THE INSTANCE, AND THAT IS THE ONLY WAY THIS CLAIM IS
    // FALSIFIABLE. Through the front door both orderings answer 403: under the
    // wrong one `validate()` throws first, Livewire catches the
    // ValidationException, re-renders — and `render()` authorizes too, so the
    // response is forbidden either way. A test driven through `->call()` would
    // be named for a claim it does not make (decisions 352, 397). Calling the
    // action directly is what separates "the gate refused" from "the form
    // refused, and then the gate did".
    //
    // Not exploitable: nothing past the gate runs on either ordering. What it
    // buys is one shape instead of two in a file where `lookUp()` authorizes on
    // line one.
    //
    // ⚠️ MUTATION: move `$this->authorize(AdminAccess::GATE);` below
    // `$this->validate([...])` in raiseToPhi(), recordExecution() or
    // endAgreement(). Each reddens on its own leg with a ValidationException.
    $component = Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->instance();

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    // Every field left empty, so the validator has plenty to say if it is asked
    // first.
    expect(fn (): mixed => $component->raiseToPhi(app(TenantClassification::class)))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): mixed => $component->recordExecution(app(BaaRecords::class), app(Documents::class)))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): mixed => $component->endAgreement(app(BaaRecords::class)))
        ->toThrow(AuthorizationException::class);
});

test('a lookup whose audit row cannot be written puts no business in view', function (): void {
    // ⚠️ THE AUDIT WRITE CAN THROW, AND THAT IS THE CORRECT BEHAVIOUR RATHER
    // THAN AN OVERSIGHT — which is worth pinning precisely because it looks like
    // one. The alternative reading is "catch it and show the business anyway",
    // and that would put a tenant's name, classification and signer details on
    // screen with nothing anywhere recording the read: the single thing the
    // `business.viewed_by_staff` entry exists to prevent. An unwritable audit
    // log is an operational failure and reads as one; there is no sentence an
    // admin could act on, and "there is no business N" would be a lie about a
    // business that exists.
    //
    // ⚠️ MUTATION: wrap the `$audit->record(...)` call in PhiTenants::lookUp()
    // in a try/catch that swallows, or move `$this->businessId = $id;` above it
    // and swallow. This reddens: nothing throws, and the read proceeds unaudited.
    Event::listen(
        'eloquent.creating: '.AuditLogEntry::class,
        fn (): never => throw new RuntimeException('audit_log is unwritable'),
    );

    expect(fn (): mixed => Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp'))
        ->toThrow(RuntimeException::class, 'audit_log is unwritable');
});

test('the screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(PhiTenants::class)
        ->assertForbidden();
});

test('the route is behind the admin gate too, because hiding a nav item is not authorization', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $this->actingAs($this->admin)
        ->get(route('admin.phi-tenants'))
        ->assertForbidden();
});
