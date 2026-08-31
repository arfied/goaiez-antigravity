<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Admin\MailSending;
use App\Models\PlatformMailSend;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Mail\MailQuota;
use App\Services\Mail\MailSendRate;
use App\Support\Admin\AdminNav;
use App\Support\Admin\NavItem;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The email sending meter — T137 R3, decision 4442's owed screen (4600)
|--------------------------------------------------------------------------
|
| `MailQuota::reading()` existed for five days with no reader, while its own
| docblock described it as what "the admin screen R3 asks for". Its only
| consumers were two exception messages asking for ['used'].
|
| ⚠️ THE ROUTE GATE IS DRIVEN THROUGH A REAL GET, NOT THROUGH `Livewire::test()`
| (809): a component test runs no middleware, so an authorization assertion made
| through it says nothing about the route. And one real 200 is not optional —
| 570's defect was every /admin screen 500-ing on a missing layout, invisible to
| every component test in the suite.
|
| ⚠️ NOTHING HERE ASSERTS A FIGURE THE SCREEN COMPUTED ITSELF. Every number on
| the page comes out of one `reading()` call and the template does no
| arithmetic, which is 4485's rule: two renderers remembering one rule is how
| two panels came to give two answers about one business at one moment.
|
*/

beforeEach(function (): void {
    // The real role rather than a stubbed gate, `SendingControlsAdminTest`'s
    // reason: half the subject is who may read this, and a stub answers "yes"
    // for a tenant's own owner.
    $this->admin = User::factory()->withSecondFactor()->create([
        'name' => 'Platform Staff',
        'role' => UserRole::SuperAdmin,
    ]);

    config([
        'mail.default' => 'array',
        'mail.from.address' => 'noreply@'.platformSendingDomain(),
    ]);
});

function fillMailWindow(int $count): void
{
    PlatformMailSend::factory()->count($count)->create([
        'mailer' => 'array',
        'sending_account' => 'noreply@'.platformSendingDomain(),
        'sent_at' => now(),
    ]);
}

test('the meter shows the window against the ceiling', function (): void {
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 1_000, 'test');

    fillMailWindow(120);

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('120')
        ->assertSee('1,000')
        // Outcome language (`22`): what is happening to the mail, never which
        // service measured it.
        ->assertSee('Email is sending');
});

test('a ceiling nobody has stated reads as an outage and names the row to set', function (): void {
    // ⛔ **THE STATE R16's ACTIVATION REACHES IN ONE `.env` LINE** (4604). The
    // `smtp` mailer carries no seeded ceiling, so a deployment that sets
    // `MAIL_MAILER=smtp` and nothing else sends nothing at all — and until this
    // screen existed the only evidence was in `failed_jobs`.
    //
    // ⚠️ **"Email is stopped", NOT A DASH AND NOT A ZERO.** A dash is the
    // honest rendering of something unmeasured (`RateReading`, 4484); this is
    // measured exactly and its consequence is certain, so greying it out would
    // describe a gap in our knowledge where there is an outage in the product.
    config(['mail.default' => 'smtp']);

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('Email is stopped')
        ->assertSee('No email is going out.')
        // The row, named on the page. A refusal that cannot say what to set is
        // one somebody has to come and ask about.
        ->assertSee('mail.daily_send_ceiling.smtp');
});

test('the screen states the reserve it is applying, not the row, when the two differ', function (): void {
    // ⛔ **4485's SHAPE, WHICH THIS SCREEN COULD HAVE WALKED STRAIGHT INTO.**
    // The reserve is clamped one below the ceiling, so an SES sandbox
    // deployment — ceiling 200, seeded reserve 200 — holds back 199. The Ops
    // settings screen shows the row; this shows the effect. Two screens giving
    // two answers about one deployment is 3418's shape, and the fix is to state
    // the derivation rather than to pick a figure.
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 200, 'test');
    PlatformSetting::write('mail.ceiling_customer_reserve', 200, 'test');

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        // The effective reserve, and the customer stop derived from it.
        ->assertSee('199')
        // And the configured row beside it, with why they differ.
        ->assertSee('is set in Settings and is being held');
});

test('the reserve is stated plainly when nothing is being clamped', function (): void {
    // The other branch, so the sentence above is not the only thing this panel
    // can ever render — 398's unfalsifiable guard from the other end.
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 1_000, 'test');
    PlatformSetting::write('mail.ceiling_customer_reserve', 200, 'test');

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('Messages, set in Settings.')
        ->assertDontSee('is set in Settings and is being held');
});

test('the screen and the alert agree about whether the account is alerting', function (): void {
    // ⛔ **THE DEFECT 4485 PREDICTS, MEASURED HERE RATHER THAN ARGUED.**
    // `reading()` compared a rounded ratio and the alert the unrounded
    // quotient, so 15,999 of 20,000 — 0.79995, which rounds to 0.8 — read as
    // alerting on the screen while no alert had been raised. The screen has to
    // say what the alert did.
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 20_000, 'test');
    PlatformSetting::write('mail.ceiling_alert_percent', 80, 'test');

    fillMailWindow(15_999);

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('Email is sending')
        ->assertDontSee('Close to the limit');
});

test('the screen says so when the window has crossed the alert threshold', function (): void {
    // The positive control for the test above, which would otherwise pass
    // against a screen that never says "Close to the limit" at all.
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 100, 'test');
    PlatformSetting::write('mail.ceiling_alert_percent', 80, 'test');

    fillMailWindow(85);

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('Close to the limit')
        ->assertSee('An alert has already been raised');
});

test('the screen names no recipient, because the meter stores none', function (): void {
    // ⚠️ **THE ONLY ADDRESS ON THIS PAGE IS OURS.** `platform_mail_sends`
    // deliberately holds no address, subject or notification class — its own
    // test asserts the column set — so there is no recipient here to leak. The
    // sending account is the Workspace user or our from address.
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 1_000, 'test');

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('noreply@'.platformSendingDomain())
        ->assertDontSee('@example.test');
});

test('an owner cannot read the platform meter', function (): void {
    Livewire::actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->test(MailSending::class)
        ->assertForbidden();
});

test('the route is gated too, because a component test runs no middleware', function (): void {
    // Decision 809, stated rather than assumed.
    $this->actingAs(User::factory()->withSecondFactor()->create(['role' => UserRole::Owner]))
        ->get(route('admin.mail-sending'))
        ->assertForbidden();
});

test('an unauthenticated visitor cannot reach the meter', function (): void {
    // Its own test: `$this->actingAs()` persists for the rest of the method, so
    // a guest GET written under an authenticated one is still authenticated.
    $this->get(route('admin.mail-sending'))->assertRedirect();
});

test('the meter actually renders over a real request', function (): void {
    // ⚠️ 570'S DEFECT: every /admin screen 500'd on a missing layout, invisible
    // because component tests render no layout and the only real GETs asserted
    // a refusal, which short-circuits in middleware. And it needs a second
    // factor — `RequiresTwoFactor` covers the whole internal group (`28` §9.1),
    // which no component test can see.
    PlatformSetting::write(MailQuota::ceilingKeyFor('array'), 1_000, 'test');

    $this->actingAs($this->admin)
        ->get(route('admin.mail-sending'))
        ->assertOk()
        ->assertSee('Email sending')
        ->assertSee('What stops first');
});

test('an internal account without a second factor cannot reach the meter', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
        ->get(route('admin.mail-sending'))
        ->assertRedirect(route('two-factor.setup'));
});

test('the nav offers the meter to platform staff and to nobody else', function (): void {
    // A meter nobody can find is most of the way to one that does not exist —
    // which is what 4442 recorded for five days.
    expect(AdminNav::for($this->admin)->flatten()
        ->contains(fn (NavItem $item): bool => $item->route === 'admin.mail-sending'))
        ->toBeTrue();

    expect(AdminNav::for(User::factory()->create(['role' => UserRole::Owner]))->flatten()
        ->contains(fn (NavItem $item): bool => $item->route === 'admin.mail-sending'))
        ->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The second limit — 4432's per-second rate, on the screen (4648, 4650)
|--------------------------------------------------------------------------
|
| ⛔ IT IS ON THIS PAGE BECAUSE NOTHING ELSE WOULD EVER MAKE AN OPERATOR SET IT.
| An unstated ceiling refuses every message and announces itself in `failed_jobs`
| within a minute. An unstated rate refuses nothing — so the row would sit empty
| for ever with no symptom, which is a control with no writer (272) on a
| fail-open limit. Being visible beside the fail-closed one is the answer.
|
*/

test('an unstated sending rate reads as unpaced rather than as an outage, and names its row', function (): void {
    // ⚠️ **THE WORDING IS THE SUBJECT.** Reusing the ceiling's "no email is
    // going out" here would be a false statement — mail sends perfectly well
    // unpaced — and reusing its alert state would put a red pill on a
    // deployment with nothing wrong with it.
    config(['mail.default' => 'smtp']);
    PlatformSetting::write(MailQuota::ceilingKeyFor('smtp'), 1_000, 'test');

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('Messages go out as fast as the queue drains.')
        ->assertSee('mail.send_rate_per_second.smtp')
        // ⛔ **AND THE SCREEN STILL SAYS MAIL IS SENDING**, because it is. An
        // unpaced transport is a working one.
        ->assertSee('Email is sending')
        ->assertDontSee('No email is going out.');
});

test('a stated sending rate is shown, and says what it does and does not do', function (): void {
    // ⛔ **"NOTHING IS REFUSED AND NOTHING IS DROPPED" IS THE CLAIM THIS PAGE
    // HAS TO MAKE AND THE ONE IT MUST NOT OVERSTATE** (4649, 4651). Amazon's own
    // documentation says the rate may be exceeded for short bursts and that the
    // rate it accepts can be lower than the one granted, so a page promising
    // that nothing is ever throttled would assert a protection that is not true
    // (314–316) about a limit nobody here has ever been held to.
    config(['mail.default' => 'smtp']);
    PlatformSetting::write(MailQuota::ceilingKeyFor('smtp'), 1_000, 'test');
    PlatformSetting::write(MailSendRate::rateKeyFor('smtp'), 1, 'test');

    Livewire::actingAs($this->admin)
        ->test(MailSending::class)
        ->assertOk()
        ->assertSee('/ second')
        ->assertSee('Nothing is refused and nothing is dropped')
        ->assertDontSee('Messages go out as fast as the queue drains.');
});
