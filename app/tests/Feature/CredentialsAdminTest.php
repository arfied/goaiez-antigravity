<?php

declare(strict_types=1);

use App\Enums\CredentialChangeAction;
use App\Enums\CredentialEnvironment;
use App\Enums\UserRole;
use App\Livewire\Admin\Credentials as CredentialsScreen;
use App\Models\PlatformCredential;
use App\Models\User;
use App\Services\Config\CredentialStore;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\AdminNav;
use App\Support\CredentialManifest;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Ops → Platform → Credentials (doc `38` Part 1)
|--------------------------------------------------------------------------
|
| ⚠️ THE LOAD-BEARING TESTS HERE ARE ABOUT THE PAGE'S OWN HTML, not about the
| store. `38` says "paste, never displayed again", and a Livewire public
| property lives in the component snapshot embedded in the page — so "the
| service never returns the value" is not the same claim as "the value is not
| in the DOM", and only the second one is what an operator is promised.
|
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);
    $this->admin = User::factory()->create();
});

test('an operator sets a credential through the confirmation, and it lands encrypted', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmRotate', 'google_places_key')
        ->set('draft', 'AIzaSyPASTEDBYANOPERATOR4321')
        ->set('environment', 'live')
        ->call('save')
        ->assertHasNoErrors();

    $credential = PlatformCredential::query()->findOrFail('google_places_key');

    expect($credential->value)->toBe('AIzaSyPASTEDBYANOPERATOR4321')
        ->and($credential->last_four)->toBe('4321')
        ->and($credential->environment)->toBe(CredentialEnvironment::Live)
        ->and($credential->rotated_by)->toBe('user:'.$this->admin->id);
});

test('the pasted key is gone from the component the moment it is saved', function (): void {
    // ⚠️ THE REASON THE CONFIRMATION COMES BEFORE THE PASTE RATHER THAN AFTER.
    // A confirm-then-write flow would need the secret to survive a round trip,
    // and it would sit in the page's snapshot in plaintext for as long as the
    // dialog stayed open. Here it reaches the server once, in the request that
    // writes it, and is cleared before the response renders.
    $component = Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmRotate', 'anthropic_api_key')
        ->set('draft', 'sk-ant-NEVER-IN-THE-DOM-1111')
        ->call('save');

    $component->assertSet('draft', '')
        ->assertSet('rotating', '')
        ->assertDontSee('sk-ant-NEVER-IN-THE-DOM-1111', escape: false)
        ->assertDontSee('sk-ant-NEVER', escape: false);

    // The whole serialised response, not just the rendered fragment: the
    // snapshot Livewire embeds is where a public property would survive, and it
    // is not part of what assertDontSee looks at.
    expect(json_encode($component->html()))->not->toContain('sk-ant-NEVER');
});

test('an error while saving still clears the pasted key', function (): void {
    // The branch that matters most, and the one an implementation forgets: an
    // early return that leaves the draft in place puts the secret in the
    // snapshot for as long as the operator sits reading the error — which is
    // precisely when they leave the screen open.
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmRotate', 'openai_api_key')
        // Blank is refused by the store, which is the reachable error path from
        // this screen.
        ->set('draft', '   ')
        ->call('save')
        ->assertSet('draft', '')
        ->assertSet('rotating', '');

    expect(PlatformCredential::query()->count())->toBe(0);
});

test('the board never renders a stored value, only its last four', function (): void {
    app(CredentialStore::class)->set(
        'turnstile_secret',
        'turnstile-secret-value-6789',
        'user:1',
        CredentialEnvironment::Live,
    );

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertDontSee('turnstile-secret-value-6789', escape: false)
        ->assertDontSee('turnstile-secret-value', escape: false)
        ->assertSee('6789');
});

test('the board says which keys are absent and what stops working', function (): void {
    // `38` Part 1: "the health board shows exactly which key is absent — never a
    // stack trace, never a silent stall."
    //
    // ⛔ **THIS ASSERTED A HAND-COPIED SENTENCE UNTIL 9144, AND THE SENTENCE WAS
    // FALSE.** It expected the literal "The instant audit and business
    // autocomplete stop offering results", which was the front half of a
    // `degradation` string promising that "the marketing home still renders; no
    // visitor sees an error" — while `GooglePlacesClient` read the key with
    // `PlatformCredentials::get()` and every one of the three customer-facing
    // paths answered 500. So this test proved the board rendered **a** string,
    // by holding a second copy of it, and a second copy is not a check: it went
    // green on a screen telling an operator the opposite of what was happening.
    //
    // ✅ **IT NOW ASSERTS THE MANIFEST'S OWN SENTENCE**, so what is proved is the
    // property that matters — the board shows the manifest's answer for this key
    // and not some other text — and correcting the sentence cannot leave a copy
    // behind here to go stale.
    $expected = CredentialManifest::credentials()['google_places_key']['degradation'];

    // The anti-vacuity floor. A derived assertion against an empty or trivial
    // string passes against anything, which is the failure this whole file's
    // subject is about one level up.
    expect($expected)->toBeString()
        ->and(mb_strlen($expected))->toBeGreaterThan(80);

    config()->set('credentials.google_places_key', null);

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertSee('Not configured')
        ->assertSee($expected, escape: false);
});

test('a key coming from the environment file is not shown as managed', function (): void {
    // The middle state, which a two-light board cannot express: it works, it
    // cannot be rotated from here, and clearing it here would not stop it being
    // used.
    config()->set('credentials.infobip_api_key', 'seeded-from-env');

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertSee('From the environment file');
});

test('clearing a credential is confirmed first and logged', function (): void {
    $store = app(CredentialStore::class);
    $store->set('openai_api_key', 'value-to-clear-2222', 'user:1', CredentialEnvironment::Live);

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmClear', 'openai_api_key')
        ->assertSet('clearing', 'openai_api_key')
        ->call('clear')
        ->assertSet('clearing', '');

    expect(PlatformCredential::query()->find('openai_api_key'))->toBeNull()
        ->and($store->historyFor('openai_api_key')[0]->action)->toBe(CredentialChangeAction::Cleared);
});

test('an action with nothing selected does nothing at all', function (): void {
    // The two actions read a locked property rather than an argument, so the
    // no-selection case is reachable by a client that calls them out of order.
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('save')
        ->call('clear')
        ->assertHasNoErrors();

    expect(PlatformCredential::query()->count())->toBe(0);
});

test('a user who is not platform staff cannot reach the screen', function (): void {
    // The real gate rather than the permissive one this file's beforeEach
    // defines, because the point of the assertion is the predicate.
    AdminAccess::register();

    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get(route('admin.credentials'))->assertForbidden();
});

test('the credentials screen is offered in the admin navigation', function (): void {
    // A screen nobody can find is decision 399's shape wearing a different hat:
    // the service has a caller, and the caller has no door. Asserted through
    // AdminNav rather than by rendering a page, which is where the existing
    // "every declared nav route resolves" test picks it up.
    AdminAccess::register();

    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    expect(AdminNav::for($admin)->flatten()->pluck('route'))->toContain('admin.credentials');
});

/*
|--------------------------------------------------------------------------
| ⛔ What this board does not list — 9374's owed companion line
|--------------------------------------------------------------------------
|
| ⛔ **THE OPERATOR STANDING HERE IS ASKING A DIFFERENT QUESTION FROM THE ONE
| WAVE 27 ANSWERED.** 9374 put the finding on `Admin\MailSending`, argued: the
| person holding it there is asking *"why is no mail going out"*. The person
| standing at this board is asking *"is this the list?"* and reads twenty rows
| as the answer — while on an SMTP transport the credential that decides whether
| **any** email leaves, a sign-in link included, is read by `config/mail.php`
| straight from the environment and cannot be put on this board at all.
|
| ⚠️ **DERIVED, NOT RE-TYPED.** `CredentialManifest::mailerCredential()` is the
| same call the mail screen makes, and `MailDrivers::active()` is the same
| reading of which mailer is live. A second reading of `mail.mailers.*` here
| would be `CLAUDE.md`'s 8460 — two copies agreeing until one of them moved.
| **The sentences differ because the question does; that is a companion and not
| a copy.**
|
*/

test('the credentials board says the mail transport credential is not on it', function (): void {
    // ⛔ **AN ABSENCE IS THE HARDEST THING FOR A SCREEN TO SHOW**, and this
    // board is the one whose whole promise is *"shows exactly which key is
    // absent"* — so a key it cannot show at all is the sharpest form of the
    // thing it is for. Production met exactly that: an SES transport holding
    // another vendor's username, three permanent failures, and every row here
    // green throughout.
    //
    // ⚠️ MUTATION: return `['authenticates' => false, 'key' => null]` from
    // `CredentialManifest::mailerCredential()` and this reddens — the screen
    // falls to the *"presents no credentials at all"* arm, which is a different
    // and wrong sentence.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.username' => 'AKIAEXAMPLE',
    ]);

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        // ⚠️ **NO APOSTROPHE IN THE NEEDLE.** Blade escapes it to `&#039;` and
        // `assertSee()` compares against the rendered HTML, so a needle
        // carrying one fails on the encoding rather than on the sentence.
        ->assertSee('The credential this platform sends email with is not on this board and cannot be put on it.')
        ->assertSee('MAIL_USERNAME')
        ->assertSee('MAIL_PASSWORD');
});

test('a transport that signs in with nothing is told apart from one that signs in with something we cannot see', function (): void {
    // ⛔ **TWO OUTCOMES, TWO SENTENCES.** `config/mail.php` ships `smtp`
    // pointing at `127.0.0.1:2525` with no username — an anonymous relay — and
    // telling an operator a credential is missing from a list it was never on
    // is the wasted hour with the sign reversed.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.username' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertSee('presents no credentials at all')
        ->assertDontSee('cannot be put on it');
});

test('a transport whose credential this board does hold says so rather than claiming a gap', function (): void {
    // ⛔ **THE ARM THAT MAKES THE DERIVATION NON-VACUOUS.** The Gmail API
    // transport signs in with `gmail_refresh_token`, which this register *does*
    // declare — so the honest sentence here is that nothing is missing, and it
    // names the row. Without this arm the panel would be a paragraph that always
    // says the same thing, which is 256's shape at a screen.
    config(['mail.default' => 'gmail']);

    expect(CredentialManifest::mailerCredential('gmail'))
        ->toBe(['authenticates' => false, 'key' => 'gmail_refresh_token']);

    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertSee('gmail_refresh_token')
        ->assertDontSee('cannot be put on it')
        ->assertDontSee('presents no credentials at all');
});

test('the board offers a field for the Pixabay stock-photo key', function (): void {
    $label = CredentialManifest::credentials()['pixabay_api_key']['label'] ?? null;
    expect($label)->toBe('Pixabay API key (stock photos)')
        ->and(config()->has('credentials.pixabay_api_key'))->toBeTrue();

    config()->set('credentials.pixabay_api_key', null);
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertSee('Pixabay API key (stock photos)');
});
