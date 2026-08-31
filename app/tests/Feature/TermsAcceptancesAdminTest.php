<?php

declare(strict_types=1);

use App\Enums\LegalDocumentType;
use App\Enums\ProofHashDomain;
use App\Enums\TermsAcceptanceMethod;
use App\Livewire\Admin\TermsAcceptances;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Consent\ProofHash;
use App\Services\Legal\TermsAcceptances as Acceptances;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The signup-agreements admin screen — T176 P22, decision 3995
|--------------------------------------------------------------------------
|
| The read path for `terms_acceptances`, which had a writer at both signup doors
| and no way at all to get an answer out of it. Its readers are a person
| answering a carrier's question and a person answering a subpoena; nothing in
| this application's own logic reads this record, and nothing should.
|
| ⛔ IT SHOWS AND DOES NOTHING ELSE. There is no action here beyond the lookup —
| the record is append-only, and the only thing a correction control could add is
| agreement nobody gave.
|
| ⚠️ THREE DOCUMENTS ARE ANSWERED SEPARATELY, which is the whole point of 3995's
| "three rows rather than one". The fourth test below is the one that holds it:
| a screen that looped the tenant's rows instead of the document set would render
| a tidy panel silently omitting the document nobody accepted — the one a
| reviewer is asking about.
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
 * Record one acceptance set for a business, through the only writer there is.
 *
 * A local name rather than a shared one — a duplicated global helper name is one
 * of the four causes of a run that prints zero bytes (694, 808).
 */
function acceptForAdminScreen(Business $business, TermsAcceptanceMethod $method = TermsAcceptanceMethod::Checkbox): void
{
    Tenancy::actingAs((int) $business->id, fn (): array => app(Acceptances::class)->record(
        $method,
        [
            'url' => 'https://example.test/register',
            'ip_hash' => str_repeat('b', 64),
            'user_agent' => 'Mozilla/5.0 (Macintosh)',
        ],
        'user:1',
    ));

    Tenancy::forgetAll();
}

/*
|--------------------------------------------------------------------------
| The answer somebody came here for
|--------------------------------------------------------------------------
*/

test('the screen names each document, its version and when it was accepted', function (): void {
    publishSignupTerms('2.4');

    acceptForAdminScreen($this->business);

    $page = Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertHasNoErrors();

    // ⚠️ MUTATION: return `[]` for `standing` in `render()`. All three of these
    // redden.
    foreach ([LegalDocumentType::Terms, LegalDocumentType::SmsTerms, LegalDocumentType::Privacy] as $type) {
        $page->assertSee($type->title());
    }

    $page->assertSee('2.4')
        ->assertSee('Bright Smile Dental');
});

test('the proof is on the screen — the page, the hashed address, the agent, the box and the wording', function (): void {
    // ⛔ THIS IS THE EVIDENCE, AND A SCREEN THAT SHOWED A VERSION NUMBER ALONE
    // WOULD ANSWER HALF THE QUESTION. A carrier reviewer asks what was on the
    // page; a version resolves to words only while the store that resolves it is
    // at hand, which is why the wording is stored and rendered rather than
    // referenced.
    //
    // ⚠️ MUTATION: delete any one of the five <dd> blocks from the history
    // section of the template. The matching assertion reddens.
    publishSignupTerms();

    acceptForAdminScreen($this->business);

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('https://example.test/register')
        ->assertSee(ProofHash::hash(ProofHashDomain::TermsAcceptances, str_repeat('b', 64)))
        ->assertSee('Mozilla/5.0 (Macintosh)')
        ->assertSee('checked_by_user')
        ->assertSee('user:1')
        // The wording verbatim, from the stored blob — never typed into the
        // template, which an architecture lint refuses.
        ->assertSee(TermsAcceptanceMethod::Checkbox->notice());
});

test('a raw address never reaches the screen, because the record cannot hold one', function (): void {
    // `29` §2 rule 21. The proof blob stores a hash, so this asserts the shape
    // rather than a redaction — there is nothing here to redact, and a screen
    // that could print an address would mean the record had stored one.
    publishSignupTerms();

    Tenancy::actingAs((int) $this->business->id, fn (): array => app(Acceptances::class)->record(
        TermsAcceptanceMethod::Checkbox,
        [
            'url' => 'https://example.test/register',
            'ip_hash' => str_repeat('c', 64),
            'user_agent' => 'Mozilla/5.0',
        ],
        'user:1',
    ));

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertDontSee('203.0.113.42')
        // ⚠️ THE SCOPED VALUE IS WHAT THE SCREEN SHOWS, not the one handed to
        // the writer (7888). The person reading this page is answering a
        // carrier or a subpoena about *this* record; what they cannot do any
        // more is carry the value to another table.
        ->assertSee(ProofHash::hash(ProofHashDomain::TermsAcceptances, str_repeat('c', 64)))
        ->assertDontSee(str_repeat('c', 64));
});

test('the screen says what the hashed address may be compared with', function (): void {
    // ⛔ THE HALF OF 7888's REMEDY THAT IS NOT CODE. `terms_acceptances` now
    // hashes the address under its own scope, so two agreements from one place
    // still match each other and match nothing anywhere else. The person
    // reading this page is answering a carrier or a subpoena and cannot tell
    // that from sixty-four hex characters — without this line, "is this the
    // same person as that other record?" gets a silent, wrong no.
    //
    // ⚠️ MUTATION: delete the `@if` block under the hashed address in the
    // template. This reddens and nothing else does.
    publishSignupTerms();

    acceptForAdminScreen($this->business);

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Compare this with other signup agreements')
        ->assertDontSee('Recorded before agreements were scoped');
});

test('a row written before the scoping says so instead of reading as comparable', function (): void {
    // ⛔ THE ERA THIS APPLICATION CANNOT FIX AND MUST NOT MISREPRESENT. No
    // back-fill was attempted — the raw address is gone, these rows are
    // append-only (295), and copies have already left through exports — so a
    // row with no `hash_domain` carries the unscoped construction for ever.
    // Rendering it beside a scoped one with no distinction is the misreading
    // that costs somebody a wrong answer to a regulator.
    publishSignupTerms();

    acceptForAdminScreen($this->business);

    // The pre-scoping shape, written the only way a pre-scoping row could be:
    // straight into the column, because the writer cannot produce one any more.
    Tenancy::actingAs((int) $this->business->id, function (): void {
        $row = TermsAcceptance::query()->orderByDesc('id')->firstOrFail();

        DB::table('terms_acceptances')
            ->where('id', $row->id)
            ->update(['proof' => json_encode([
                'url' => 'https://example.test/register',
                'ip_hash' => str_repeat('d', 64),
                'user_agent' => 'Mozilla/5.0 (Macintosh)',
                'checkbox_state' => 'checked_by_user',
            ], JSON_THROW_ON_ERROR)]);
    });

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Recorded before agreements were scoped');
});

test('a document with no acceptance says so rather than leaving a blank', function (): void {
    // ⛔ THE ABSENCE IS THE FINDING, AND THIS IS THE TEST THAT HOLDS 3995's
    // "three rows rather than one" AT THE READ END. The screen loops
    // `SignupTerms::DOCUMENTS`, so a document nobody accepted appears and says
    // so; a screen that looped the tenant's own rows would omit exactly the
    // document a reviewer is asking about, and would look complete doing it.
    //
    // ⚠️ MUTATION: change `standing()` to map over `$acceptances->historyFor()`
    // instead of `SignupTerms::DOCUMENTS`. The SMS & Communications Terms then
    // vanish from the panel and this reddens.
    publishSignupTerms();

    // Only the Privacy Policy, written directly through the service's own
    // writer is not possible — `record()` writes all three — so this business
    // is one that accepted nothing at all, the state an account provisioned
    // before this record existed is in.
    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee(LegalDocumentType::SmsTerms->title())
        ->assertSee('No record of this document being accepted')
        ->assertSee('Nothing on record');
});

test('a second acceptance is a second row, and the earlier one stays', function (): void {
    publishSignupTerms('1.0');

    acceptForAdminScreen($this->business);

    publishSignupTerms('1.1');

    acceptForAdminScreen($this->business);

    // ⚠️ MUTATION: return only the newest row from `historyFor()`. The older
    // version disappears and this reddens — which matters because an acceptance
    // is a dated event and what a business agreed to in August is still what
    // they agreed to in August.
    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('1.0')
        ->assertSee('1.1');
});

test('the two methods are told apart in words, not left to a slug', function (): void {
    // A ticked box and a notice beside a sign-on button are not equally strong,
    // and `22` says every state is a word. The column exists to tell them apart;
    // a screen printing `sso_continue` would be naming the mechanism rather than
    // what happened.
    publishSignupTerms();

    acceptForAdminScreen($this->business, TermsAcceptanceMethod::SsoContinue);

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('A notice beside the button they pressed')
        ->assertSee('No box — acceptance was by notice')
        ->assertDontSee('sso_continue');
});

/*
|--------------------------------------------------------------------------
| The tenant boundary
|--------------------------------------------------------------------------
*/

test('one tenant\'s acceptances never appear on another tenant\'s page', function (): void {
    // ⛔ THE ISOLATION TEST. `terms_acceptances` is FORCE ROW LEVEL SECURITY and
    // carries a global scope, and this screen is the first thing that reads it
    // across the tenant boundary — so the proof that the two layers hold is a
    // screen showing one business's evidence and none of the other's.
    //
    // ⚠️ MUTATION, AND THE RESULT IS WORTH RECORDING RATHER THAN CLAIMING.
    // Replacing `TermsAcceptance::query()` with
    // `TermsAcceptance::withoutGlobalScopes()` inside `historyFor()` leaves this
    // test GREEN — row-level security refuses on its own, which is what a
    // FORCE-RLS table connected to as a non-owner role is supposed to do. So
    // this test pins the **composed** boundary and cannot, by itself, tell you
    // the global scope is still there; the `arch()` scope lint is what holds
    // that half. Saying so beats a comment claiming a mutation that does not
    // redden (352, 397, 565).
    //
    // What it does fail on: `viewing()` losing its `Tenancy::actingAs()`
    // wrapper, or pointing it at the wrong id — both are refused by the
    // service's own tenant guard, which is driven red directly in
    // `TermsAcceptanceReaderTest`.
    publishSignupTerms('7.7');

    acceptForAdminScreen($this->business);

    $other = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Northgate Plumbing',
    ]);

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $other->id)
        ->call('lookUp')
        ->assertSee('Northgate Plumbing')
        // The other tenant's evidence, none of which this page may show.
        ->assertDontSee('7.7')
        // ⚠️ THE SCOPED VALUE, which is what the other tenant's row actually
        // holds. Asserting the pre-scoping input here would pass without the
        // page hiding anything, which is a test passing for the wrong reason.
        ->assertDontSee(ProofHash::hash(ProofHashDomain::TermsAcceptances, str_repeat('b', 64)))
        ->assertSee('Nothing on record');
});

test('the business in view cannot be set from the client', function (): void {
    // ⚠️ WITHOUT `#[Locked]` THIS IS THE WHOLE AUDIT TRAIL WALKED AROUND. The
    // property arrives in the update payload, so anybody who can reach this
    // component could point it at any business and let `render()` read that
    // tenant's acceptance proof with `lookUp()` never called and nothing
    // recorded anywhere.
    //
    // ⚠️ MUTATION: remove `#[Locked]` from `$businessId`. This reddens.
    publishSignupTerms();

    acceptForAdminScreen($this->business);

    expect(fn (): mixed => Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('businessId', $this->business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

/*
|--------------------------------------------------------------------------
| The lookup, and the trace it leaves
|--------------------------------------------------------------------------
*/

test('a resolved lookup is recorded in the looked-up tenant own log', function (): void {
    // ⚠️ THIS SCREEN READS ACROSS THE TENANT BOUNDARY AND WHAT IT SHOWS IS THE
    // ACCOUNT HOLDER'S OWN PROOF — the page they were on, a hashed address,
    // their browser and the actor string naming them. A read that left no trace
    // lets staff walk the business id space with nothing anywhere to say they
    // did. `PhiTenants::lookUp()`'s argument, and the same entry.
    //
    // ⚠️ MUTATION: delete the `$audit->record('business.viewed_by_staff', …)`
    // call from `lookUp()`. This reddens.
    publishSignupTerms();

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
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

test('a lookup that resolves to nothing records nothing and clears the one in view', function (): void {
    // Two halves. There is no tenant to file a miss under, and "there is no
    // business 999999" discloses nothing about a business that does not exist —
    // and an admin looking at business 12 who then mistypes must not still be
    // looking at 12.
    //
    // ⚠️ MUTATION: delete the two `$this->businessId = null;` lines from
    // `lookUp()`. The second half reddens on both branches. Resolving a real
    // business first is what makes the clearing observable at all — asserting
    // null from a fresh component would be asserting the property's initial
    // value (411's shape).
    publishSignupTerms();

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', '999999')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        // `(int) 'not a number'` is 0, which would otherwise be looked up as
        // business 0.
        ->set('lookup', 'not a number')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null);

    Tenancy::actingAs($this->business->id, function (): void {
        // One entry, from the two resolved lookups' worth of misses — the misses
        // wrote nothing.
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(2);
    });
});

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
*/

test('the screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(TermsAcceptances::class)
        ->assertForbidden();
});

test('the route is behind the admin gate too, because hiding a nav item is not authorization', function (): void {
    // ⚠️ `Livewire::test()` RUNS NO MIDDLEWARE (809), so the test above says
    // nothing about the route. This is the half that does.
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $this->actingAs($this->admin)
        ->get(route('admin.terms-acceptances'))
        ->assertForbidden();
});

test('an admin reaches the screen from the console nav', function (): void {
    // Evidence nobody can find is most of the way to evidence that does not
    // exist — decision 261's rule, that the nav links only to what exists.
    $this->actingAs($this->admin)
        ->get(route('admin.terms-acceptances'))
        ->assertOk()
        ->assertSee('Signup agreements');
});
