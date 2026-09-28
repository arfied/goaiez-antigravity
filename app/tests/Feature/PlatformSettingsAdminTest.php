<?php

declare(strict_types=1);

use App\Enums\Plan;
use App\Livewire\Admin\PlatformSettings as SettingsScreen;
use App\Models\PlanEntitlement;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Content\Publishing;
use App\Services\Messaging\SendingGuard;
use App\Services\Ops\OperatorAlerts;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\RecordingAnnouncementAttestation;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\OperatedElsewhere;
use App\Support\DefaultsManifest;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Ops → Platform → Settings (`38` Part 2's editor)
|--------------------------------------------------------------------------
|
| The screen exists so `DefaultsRegistry` has a caller — a registry nobody can
| edit is a config file with extra steps, and a service written, documented and
| uncalled is this codebase's most repeated defect (272, 377, 399, five times
| found).
|
| ⚠️ THE PRICES ARE READ-ONLY AND THE TESTS SAY SO RATHER THAN LEAVING IT TO BE
| INFERRED FROM AN ABSENT BUTTON. `38` D-151 requires a price edit to create the
| matching Stripe Price in the same breath; Cashier is row 22 and is not built,
| so an editor here would produce a registry price that disagrees with what
| customers are charged, invisibly.
|
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);
    $this->admin = User::factory()->create();
});

test('an admin changes a value and the change records both sides', function (): void {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', 'public_audit.daily_budget')
        ->assertSet('draft', '250')
        ->set('draft', '40')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', '');

    $registry = new DefaultsRegistry;

    expect($registry->int('public_audit.daily_budget'))->toBe(40);

    $history = $registry->historyFor('public_audit.daily_budget');

    expect($history)->toHaveCount(1)
        ->and($history[0]->value_before)->toBeNull()
        ->and($history[0]->value_after)->toBe(40)
        ->and($history[0]->actor)->toBe('user:'.$this->admin->id);
});

test('a number typed into the box is stored as a number', function (): void {
    // ⚠️ A budget typed into a text input arrives as a string, and `"40"` stored
    // where `40` was meant makes every raw read of the row wrong. The registry
    // coerces on the way out, so this is the second of two guards — but the Ops
    // screen is the one place a human can introduce it.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', 'public_audit.daily_budget')
        ->set('draft', '40')
        ->call('save');

    expect(PlatformSetting::query()->find('public_audit.daily_budget')->value)->toBe(40);
});

test('a money figure typed with a decimal point is stored as text, not as a float', function (): void {
    // Money is integer cents here (`18` §Money handling). Accepting `199.99` as
    // a float would be accepting the bug; storing it as the string it was typed
    // as makes the mistake visible on the screen instead of arithmetically
    // correct-looking and wrong by a factor of a hundred.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', 'public_audit.daily_budget')
        ->set('draft', '199.99')
        ->call('save');

    expect(PlatformSetting::query()->find('public_audit.daily_budget')->value)->toBe('199.99');
});

test('an admin puts a value back to its default', function (): void {
    (new DefaultsRegistry)->set('public_audit.daily_budget', 40, 'staff:1');

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('resetToSeed', 'public_audit.daily_budget')
        ->assertHasNoErrors();

    expect((new DefaultsRegistry)->int('public_audit.daily_budget'))->toBe(250);
});

test('the box that mutes the pager refuses a window measured in years', function (): void {
    // ⛔ **THE DEFECT THIS SLICE CLOSED, DRIVEN THROUGH THE SCREEN AN OPERATOR
    // ACTUALLY TYPES INTO** (7580-7599). `parse()` is a type coercion and
    // nothing else — a digits-only match and a cast — so `1051200` reached the
    // registry as a perfectly good integer and muted every recurrence of every
    // single-subject alert for two years.
    //
    // ⛔ **THE GUARD IS NOT HERE AND THAT IS DELIBERATE.** It is in
    // `DefaultsRegistry::set()`, which `ArchitectureTest` makes the only writer
    // of a registry store — a rule attached to this component would be one
    // `defaults:sync`, a console command or the next screen routes around. What
    // this test proves is the half that belongs to the screen: the refusal
    // arrives as something an operator can read and act on rather than as a 500,
    // and the draft they typed is still in the box.
    //
    // ⚠️ MUTATION: delete the `catch (InvalidArgumentException …)` arm from
    // `save()`. The exception escapes and this reddens.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', OperatorAlerts::QUIET_KEY)
        ->assertSet('draft', '60')
        ->set('draft', '1051200')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', OperatorAlerts::QUIET_KEY);

    expect(PlatformSetting::query()->find(OperatorAlerts::QUIET_KEY))->toBeNull()
        ->and((new DefaultsRegistry)->int(OperatorAlerts::QUIET_KEY))->toBe(60);
});

test('the same box still takes a day, because the ceiling is a bound and not a ban', function (): void {
    // ⚠️ **THE OTHER ARM, AND IT IS WHAT KEEPS THIS FROM BEING A MUTE NARROWED
    // INTO UNUSABILITY.** A multi-day vendor outage where the same kind and
    // subject should ring once a day rather than every hour is the stated
    // legitimate use, and it goes through untouched.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', OperatorAlerts::QUIET_KEY)
        ->set('draft', (string) (24 * 60))
        ->call('save')
        ->assertSet('editing', '');

    expect((new DefaultsRegistry)->int(OperatorAlerts::QUIET_KEY))->toBe(24 * 60);
});

test('the screen refuses a key the manifest does not declare', function (): void {
    // A Livewire action argument is request input: it arrives in the update
    // payload and nothing about it is trustworthy. The registry is what refuses
    // it, and the refusal is shown rather than swallowed.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', 'made.up.key')
        ->assertSet('editing', '');

    expect(PlatformSetting::query()->count())->toBe(0);
});

test('the screen shows every declared key, its default, and whether it moved', function (): void {
    (new DefaultsRegistry)->set('public_audit.daily_budget', 40, 'staff:1');

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertSee('public_audit.daily_budget')
        ->assertSee('credits.rate.ai_retail_multiple')
        ->assertSee('billing.trial_days')
        // Seed vs current, which is what the panel is for.
        ->assertSee('Changed')
        ->assertSee('Default');
});

test('the screen says out loud when nothing is waiting on a decision', function (): void {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertSee('Nothing is waiting on a decision')
        ->assertDontSee('plan.limited')
        ->assertDontSee('Decision 157');
});

test('the screen shows the plan prices and offers no way to edit one', function (): void {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertSee('$179.99')
        ->assertSee('$997')
        // Nothing on this screen writes an entitlement. The absence is the
        // assertion, and the sentence explaining it is what stops the next
        // person filing it as a gap.
        ->assertSee('card processor')
        ->assertDontSee('wire:click="edit(\'price.monthly_cents\')"', escape: false);

    expect(PlanEntitlement::query()->count())->toBe(0);
});

test('the settings screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertForbidden();
});

test('the prices on the screen follow the registry', function (): void {
    // The same assertion the marketing home gets, and for the same reason: a
    // screen printing the right figures proves nothing about where they came
    // from until one of them moves.
    (new DefaultsRegistry)->setEntitlement(Plan::Base, 'price.monthly_cents', 24_999, 'staff:1');

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertSee('$249.99')
        ->assertDontSee('$179.99');
});

/*
|--------------------------------------------------------------------------
| The one setting with a precondition (4505, 4514)
|--------------------------------------------------------------------------
*/

/**
 * A predicate for `assertDispatched('toaster:received', …)`.
 *
 * ⚠️ **UNIQUELY NAMED, AND NOT `toastCarrying()`.** That helper is declared in
 * `Feature/PhiTenantsAdminTest`, so it exists when the whole suite runs and
 * does not when this file runs alone — a borrowed global is a test that passes
 * for a reason outside itself (`CLAUDE.md` 694/808).
 */
function settingsToast(string $type, string $contains): callable
{
    return fn (string $name, array $params): bool => ($params['type'] ?? null) === $type
        && is_string($params['message'] ?? null)
        && str_contains($params['message'], $contains);
}

test('turning call recording on is refused here, in words the operator can act on', function (): void {
    // ⛔ **THE REFUSAL WAS CORRECT AND ARRIVED AS A 500** (4514).
    // `RecordingAnnouncementNotAttested` is a `RuntimeException`, so it fell
    // straight past `save()`'s `catch (InvalidArgumentException)` — and this
    // screen is where step 7 of the voice activation runbook (4423) sends an
    // operator. A correct decision rendered as an error page is one somebody
    // reports as a bug in the switch.
    //
    // ⚠️ MUTATION: delete the `catch (RecordingAnnouncementNotAttested …)` block
    // from `PlatformSettings::save()` — this reddens with the exception escaping
    // the component.
    // ⚠️ **IT ARRIVES THROUGH `toggle()` SINCE 5880**, because `voice.enabled`
    // seeds a boolean and every boolean-seeded key is now a switch. The
    // precondition is the registry's and did not move; what moved is the door it
    // is reached through, and a refusal that stopped being caught on the new door
    // would be the 4514 defect returning.
    //
    // ⚠️ MUTATION: delete the `catch (RecordingAnnouncementNotAttested …)` block
    // from `PlatformSettings::toggle()` — this reddens with the exception
    // escaping the component.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', RecordingAnnouncement::SWITCH_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'attested the recording announcement'));

    expect((new DefaultsRegistry)->value(RecordingAnnouncement::SWITCH_KEY))->toBeFalse();
});

test('turning it on goes through once an operator has attested', function (): void {
    // The other half, so the refusal above is about the attestation and not
    // about the key.
    app(RecordingAnnouncement::class)->attest(new RecordingAnnouncementAttestation(
        statementVersion: RecordingAnnouncement::STATEMENT_VERSION,
        attestedBy: 'support:1',
        announcementMediaId: 'announce-8k-ulaw.wav',
        proof: ['channel' => 'console'],
    ));

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', RecordingAnnouncement::SWITCH_KEY)
        ->assertDispatched('toaster:received', settingsToast('success', 'Turned on'));

    // ⚠️ THE TYPE, NOT THE TRUTHINESS. `'True'` is truthy and is what the defect
    // this screen was fixed for used to store.
    expect((new DefaultsRegistry)->value(RecordingAnnouncement::SWITCH_KEY))->toBe(true);
});

/*
|--------------------------------------------------------------------------
| Switches (5880–5883)
|--------------------------------------------------------------------------
|
| ⛔ WHAT THESE ARE ABOUT, BECAUSE THE HEADING DOES NOT SAY IT. Every registry
| value rendered as `type="text"` and `parse()` turned an input into a boolean on
| the exact lowercase `true` and on nothing else — so `True`, `1`, `yes` and `on`
| were stored as **strings**, and every reader in this application tests strictly
| (`Publishing::canWriteToSite()` is `!== true`). The setting stayed off while
| this screen showed the typed value back as the current one under a "Changed"
| chip. **It looked configured**, and the switch it mattered most on is the one
| that authorises writing to a stranger's website.
|
| It was found by somebody asking whether it ought to be a toggle. No test failed.
|
*/

test('the string spelling that used to look configured can no longer be typed at all', function (): void {
    // ⛔ THE DEFECT, IN THE SHAPE THAT SURVIVES THE FIX. Reproduced before
    // anything was changed: `edit()` → `set('draft', 'True')` → `save()` stored
    // the *string* `'True'` on `actuation.enabled`, `DefaultsRegistry::value()`
    // read it back as `'True'`, and the screen printed it as the current value.
    // What the fix removes is the door: there is no draft to set, because the
    // key cannot be opened for typing.
    //
    // ⚠️ MUTATION: delete the `if ($this->isBoolean($key))` block from
    // `PlatformSettings::edit()` — this reddens on `editing`, and the two
    // assertions below then reproduce the original defect exactly.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', Publishing::SWITCH_KEY)
        ->assertSet('editing', '')
        ->assertDispatched('toaster:received', settingsToast('error', 'on or off'))
        // The draft is the only thing `save()` writes from, and it is empty, so
        // even a crafted `save` after a crafted `edit` reaches nothing.
        ->set('draft', 'True')
        ->call('save');

    expect(PlatformSetting::query()->find(Publishing::SWITCH_KEY))->toBeNull()
        ->and((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(false);
});

test('a switch writes a real boolean and never a string', function (): void {
    // ⚠️ THE TYPE IS THE ASSERTION. `toBeTruthy()` would pass on `'True'`, which
    // is the value the defect stored — so a test written that way would have
    // gone green against the bug it exists to hold closed.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', 'crm.tasks_enabled')
        ->assertDispatched('toaster:received', settingsToast('success', 'Turned off'));

    $stored = PlatformSetting::query()->find('crm.tasks_enabled')->value;

    expect($stored)->toBe(false)
        ->and($stored)->toBeBool()
        ->and((new DefaultsRegistry)->value('crm.tasks_enabled'))->toBe(false);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', 'crm.tasks_enabled');

    expect(PlatformSetting::query()->find('crm.tasks_enabled')->value)->toBe(true);
});

test('a switch pressed against a row already holding a string repairs it', function (): void {
    // ⛔ THE ONE ROW ON THE PLATFORM THAT COULD ALREADY CARRY THE DEFECT IS THE
    // ONE THE FIX MUST NOT MISS. `! 'True'` is `false`, so a toggle spelled as a
    // negation would answer a press on a switch reading Off by writing `false`
    // and leaving it reading Off for ever. `$current !== true` is what makes the
    // press agree with what the operator can see.
    //
    // ⚠️ MUTATION: change `$registry->value($key) !== true` to `! $registry->
    // value($key)` in `PlatformSettings::toggle()` — this reddens with `false`.
    (new DefaultsRegistry)->set('crm.tasks_enabled', 'True', 'staff:1');

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', 'crm.tasks_enabled');

    expect(PlatformSetting::query()->find('crm.tasks_enabled')->value)->toBe(true);
});

test('a switch says which state it is in, in words, and shows a stored value that is neither', function (): void {
    // `22`: colour is never the sole indicator, and an on/off state needs a
    // label. The second half is the honest reading of a row holding `'True'` —
    // it is Off, and saying only "Off" would leave an operator staring at a
    // "Changed" chip with nothing to explain it.
    (new DefaultsRegistry)->set('crm.tasks_enabled', 'True', 'staff:1');

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertSee('Turn on')
        ->assertSee('everything treats it as off')
        ->assertSeeHtml('role="switch"')
        ->assertSeeHtml('aria-checked="false"')
        // ⚠️ WCAG 2.5.3 (Label in Name): the accessible name must contain the
        // visible label. Twenty switches on one screen would otherwise announce
        // as twenty identical "Turn on"s to somebody tabbing between them, and
        // an `aria-label` of the key alone would fix that by breaking 2.5.3.
        ->assertSeeHtml('aria-label="Turn on crm.tasks_enabled"');
});

/*
|--------------------------------------------------------------------------
| The one switch with a second press (5883)
|--------------------------------------------------------------------------
*/

test('the first press on the site-write switch writes nothing and names what would become possible', function (): void {
    // ⚠️ MUTATION: delete the `if ($key === Publishing::SWITCH_KEY && $next)`
    // arm from `PlatformSettings::toggle()` — this reddens on `confirming`, and
    // then again on the row, because the fall-through writes the value.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY)
        ->assertSet('confirming', Publishing::SWITCH_KEY)
        // Plain words about the consequence, not the key and not the value.
        ->assertSee("Turn on changes to customers' websites?", escape: false)
        ->assertSee("Those are other people's websites.", escape: false);

    expect(PlatformSetting::query()->find(Publishing::SWITCH_KEY))->toBeNull()
        ->and((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(false);
});

test('the confirmation states what THIS deployment has bound, and asks the container to find out', function (): void {
    // ⛔ **THE DEFECT THIS REPLACES WAS A SENTENCE, AND IT WAS FALSE IN
    // PRODUCTION AT THE MOMENT OF THE PRESS** (6121, 6127(c), 6184).
    // `actuation.enabled`'s Ops description said *"the only adapter that exists
    // reports itself unwritable, so turning this on today changes nothing"* —
    // while `WordPressAdapter` existed and `CMS_DRIVER=wordpress` was deployed
    // (5913). **No string in the repository could have been right**, because the
    // answer lives in an environment variable read at config-cache time.
    //
    // ⚠️ **THE DRIVER IS SWITCHED THROUGH THE REAL BINDING RATHER THAN A FAKE**
    // — `AppServiceProvider::registerCmsAdapter()` reads
    // `services.cms.driver` when the contract is resolved — so this drives the
    // mechanism an operator's `.env` drives and not a stand-in for it.
    config(['services.cms.driver' => 'log']);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY)
        ->assertSee('This deployment has no website adapter connected', escape: false)
        // ⛔ **AND IT CLAIMS NO SAFETY EVEN ON THIS ARM** (5888). "Nothing would
        // be written today" is only true of today, and the next deploy changes it
        // with nobody pressing this again — which is precisely how the sentence
        // this replaces became false.
        ->assertSee('the next deploy can', escape: false)
        ->assertDontSee('This deployment has a website adapter connected', escape: false);

    config(['services.cms.driver' => 'wordpress']);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY)
        ->assertSee('This deployment has a website adapter connected', escape: false)
        ->assertDontSee('This deployment has no website adapter connected', escape: false);
});

test('the second press turns it on, and it is the only thing that can', function (): void {
    // ⛔ DECISION 220's PATTERN. `Publishing::authoriseSiteWrites()` takes PHP's
    // literal `true` type, so `confirmSiteWrites()` is the one line in the
    // application that can produce a value for this key — and it is unreachable
    // until `confirming` holds the key.
    //
    // ⚠️ MUTATION: delete the `if ($this->confirming !== Publishing::SWITCH_KEY)`
    // guard from `confirmSiteWrites()` — the first half of this test reddens,
    // because the key goes true with no first press at all.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('confirmSiteWrites');

    expect((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(false);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY)
        ->call('confirmSiteWrites')
        ->assertSet('confirming', '')
        ->assertDispatched('toaster:received', settingsToast('success', 'may now change customers'));

    expect((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(true)
        ->and(PlatformSetting::query()->find(Publishing::SWITCH_KEY)->value)->toBeBool();
});

test('turning site writes off takes one press, in an incident', function (): void {
    // ⚠️ THE ASYMMETRY IS DELIBERATE and is the call `DefaultsRegistry::
    // assertPreconditionsMet()` already made for `voice.enabled`: a switch you
    // cannot turn off in an incident is worse than the thing it was protecting
    // against. The ceremony guards the direction that creates the liability.
    (new DefaultsRegistry)->set(Publishing::SWITCH_KEY, true, 'staff:1');

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY)
        ->assertSet('confirming', '')
        ->assertDispatched('toaster:received', settingsToast('success', 'will not write'));

    expect((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(false);
});

test('authorising site writes is recorded against the operator who did it', function (): void {
    // ⛔ ASSERTED AGAINST THE APPEND-ONLY LOG RATHER THAN AGAINST THE SETTINGS
    // ROW. `platform_settings` holds current state; `registry_changes` is what
    // `38` Part 2 requires and the only thing that can answer "who turned this
    // on". Read back through `historyFor()`, which is the registry's own reader.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY)
        ->call('confirmSiteWrites');

    $history = (new DefaultsRegistry)->historyFor(Publishing::SWITCH_KEY);

    expect($history)->toHaveCount(1)
        ->and($history[0]->value_before)->toBeNull()
        ->and($history[0]->value_after)->toBe(true)
        ->and($history[0]->actor)->toBe('user:'.$this->admin->id);
});

test('a switch cannot be moved by somebody the admin gate refuses', function (): void {
    // ⛔ THE GATE IS DROPPED **AFTER** THE MOUNT, ON 398's RULE. `mount()`
    // authorises too, so refusing from the start means the component never
    // renders and `toggle()`'s own `authorize()` is never reached — the outer
    // guard refuses first and the inner one is unfalsifiable. Deleting the
    // `authorize()` from `toggle()` would leave that version of this test green.
    //
    // ⚠️ MUTATION: delete `$this->authorize(AdminAccess::GATE);` from
    // `PlatformSettings::toggle()` — this reddens, and the row is written.
    $screen = Livewire::actingAs($this->admin)->test(SettingsScreen::class);

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $screen->call('toggle', 'crm.tasks_enabled')->assertForbidden();

    expect(PlatformSetting::query()->find('crm.tasks_enabled'))->toBeNull();
});

test('the second press cannot be made by somebody the admin gate refuses', function (): void {
    // The same shape on the press that authorises writing to a stranger's
    // website, driven the same way and for the same reason.
    $screen = Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', Publishing::SWITCH_KEY);

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $screen->call('confirmSiteWrites')->assertForbidden();

    expect((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(false);
});

test('a key that is not a switch is refused by the switch path', function (): void {
    // The mirror of `edit()`'s refusal. A Livewire action argument is request
    // input, so both doors have to hold from a crafted call rather than from the
    // template rendering one and not the other.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', 'public_audit.daily_budget')
        ->assertDispatched('toaster:received', settingsToast('error', 'not a switch'));

    expect(PlatformSetting::query()->find('public_audit.daily_budget'))->toBeNull();
});

test('the confirmation cannot be conjured from the update payload', function (): void {
    // ⛔ A PUBLIC LIVEWIRE PROPERTY IS WRITABLE FROM THE REQUEST. Without
    // `#[Locked]`, `$set('confirming', …)` followed by `confirmSiteWrites()`
    // reduces the two-step to one, on the switch that authorises writing to
    // customers' websites — a press nobody made.
    //
    // ⚠️ MUTATION: delete `#[Locked]` from `PlatformSettings::$confirming` —
    // this reddens, and the key goes true.
    $screen = Livewire::actingAs($this->admin)->test(SettingsScreen::class);

    expect(fn () => $screen->set('confirming', Publishing::SWITCH_KEY))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $screen->call('confirmSiteWrites');

    expect((new DefaultsRegistry)->value(Publishing::SWITCH_KEY))->toBe(false);
});

test('the site-write switch seeds off, so putting it back to default can never turn it on', function (): void {
    // ⚠️ `resetToSeed()` writes the manifest seed with no confirmation, which is
    // correct while that seed is `false` — it is the off direction — and would
    // silently become a second, unconfirmed on-switch the day somebody changed
    // the seed. Nothing else in this slice would notice.
    expect(DefaultsManifest::settings()[Publishing::SWITCH_KEY]['seed'])->toBe(false);
});

/*
|--------------------------------------------------------------------------
| The switches this editor may show and may not move (5900–5905)
|--------------------------------------------------------------------------
|
| ⛔ 5880 MADE EVERY BOOLEAN-SEEDED KEY A SWITCH HERE, AND `messaging.global_halt`
| IS A BOOLEAN. So the platform-wide stop acquired a second door — one press,
| either direction, no guard, no ordering, no confirmation — beside the screen
| that had been given all four deliberately (826, 1228, 2402, 3980–3983). A
| second door with no ceremony makes the careful door pointless.
|
| ⚠️ EVERY ONE OF THESE IS DRIVEN AT THE COMPONENT AND NOT AT THE TEMPLATE
| (398). A Livewire action argument arrives in the update payload, so the
| question is never what the screen renders — it is what the action does when
| somebody calls it anyway.
|
*/

test('the generic editor cannot stop sending for everyone', function (): void {
    // ⚠️ MUTATION: delete the `OperatedElsewhere::has()` arm from `toggle()` —
    // this reddens on both the toast and the key.
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('toggle', SendingGuard::OPERATOR_HALT_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'Stop and start sending'));

    expect((new DefaultsRegistry)->value(SendingGuard::OPERATOR_HALT_KEY))->toBe(false)
        ->and(app(SendingGuard::class)->haltedPlatformWide())->toBeFalse();
});

test('the generic editor cannot start sending again, by the switch or by the default', function (): void {
    $registry = new DefaultsRegistry;
    $registry->set(SendingGuard::OPERATOR_HALT_KEY, true, 'test');

    $screen = Livewire::actingAs($this->admin)->test(SettingsScreen::class);

    $screen->call('toggle', SendingGuard::OPERATOR_HALT_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'Stop and start sending'));

    // ⛔ **PUTTING A HALT BACK TO ITS DEFAULT IS STARTING SENDING AGAIN**, in one
    // press, on a screen that never says so — and the seed is `false`, so this
    // door needed closing as much as the switch did.
    //
    // ⚠️ MUTATION: delete the `OperatedElsewhere::has()` arm from
    // `resetToSeed()` — this reddens, and the platform starts sending.
    $screen->call('resetToSeed', SendingGuard::OPERATOR_HALT_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'Stop and start sending'));

    expect($registry->value(SendingGuard::OPERATOR_HALT_KEY))->toBe(true)
        ->and(app(SendingGuard::class)->haltedPlatformWide())->toBeTrue();
});

test('the generic editor cannot release the stop a machine threw, nor forge one', function (): void {
    // ⛔ **THE TWO KEYS ARE SEPARATE SO THAT `ComplianceReplies` HONOURS THE
    // OPERATOR'S AND NOT THE MACHINE'S** (3980–3983). A person moving this one
    // from here would be signing a measurement nobody took — the same objection
    // `SendingControls` makes to an operator selecting `ComplaintRate` as a
    // pause reason — and releasing it from here would leave the incident series
    // 2402 exists to answer with nothing to say.
    $registry = new DefaultsRegistry;

    $screen = Livewire::actingAs($this->admin)->test(SettingsScreen::class);

    $screen->call('toggle', SendingGuard::AUTOMATIC_HALT_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'complaint-rate watch'));

    expect($registry->value(SendingGuard::AUTOMATIC_HALT_KEY))->toBe(false);

    $registry->set(SendingGuard::AUTOMATIC_HALT_KEY, true, 'test');

    $screen->call('toggle', SendingGuard::AUTOMATIC_HALT_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'complaint-rate watch'));

    expect($registry->value(SendingGuard::AUTOMATIC_HALT_KEY))->toBe(true);
});

test('the key being edited cannot be named from the update payload', function (): void {
    // ⛔ `$editing` NAMES THE KEY `save()` WRITES. Without `#[Locked]`,
    // `$set('editing', 'messaging.global_halt')` followed by `save()` reaches
    // the write with `edit()`'s refusal never asked — 398's shape exactly.
    //
    // ⚠️ MUTATION: delete `#[Locked]` from `PlatformSettings::$editing` — this
    // reddens on the throw.
    $screen = Livewire::actingAs($this->admin)->test(SettingsScreen::class);

    expect(fn () => $screen->set('editing', SendingGuard::OPERATOR_HALT_KEY))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the write itself refuses, beneath the locked property', function (): void {
    // ⚠️ **NOT THE TEST ABOVE IN ANOTHER FORM, AND THAT IS 398's WHOLE POINT.**
    // `#[Locked]` is an outer guard: with it in place `save()`'s own refusal
    // cannot be reached through the request at all, so a suite that only drove
    // the property would leave the write path unfalsifiable and green. This
    // drives the component's method directly, with the property set the only way
    // a defect could ever set it.
    //
    // ⚠️ MUTATION: delete the `OperatedElsewhere::has()` arm from `save()` —
    // this reddens, and the halt is written as the string `'true'`.
    $this->actingAs($this->admin);

    $screen = new SettingsScreen;
    $screen->editing = SendingGuard::OPERATOR_HALT_KEY;
    $screen->draft = 'true';

    $screen->save(new DefaultsRegistry);

    expect((new DefaultsRegistry)->value(SendingGuard::OPERATOR_HALT_KEY))->toBe(false)
        ->and($screen->editing)->toBe(SendingGuard::OPERATOR_HALT_KEY);
});

test('the recording attestation cannot be typed, or destroyed by a round trip', function (): void {
    // ⛔ **THIS ONE PREDATES 5880 AND WAS FOUND BY LOOKING FOR THE CLASS RATHER
    // THAN THE INSTANCE.** The attestation is the record that gates
    // `voice.enabled` (4505); its door is `voice:announcement-attestation`,
    // which shows an operator the statement and stores what they answered
    // against the clip they named. It has no seed, so it rendered as a free-text
    // box here — and `parse()` returns a typed value as a **string**, so opening
    // it and pressing Save with nothing changed stored the JSON as text,
    // `fromStored()` answered null, and every recording stopped being kept. It
    // failed closed and it failed silently, which is the pair that hides.
    //
    // ⚠️ MUTATION: delete the `OperatedElsewhere::has()` arm from `edit()` —
    // this reddens on the toast and on `editing`.
    (new RecordingAnnouncement(new DefaultsRegistry))->attest(new RecordingAnnouncementAttestation(
        RecordingAnnouncement::STATEMENT_VERSION,
        'user:1',
        'media-clip-1',
        ['configured_on' => 'the platform number'],
    ));

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->call('edit', RecordingAnnouncement::SETTING_KEY)
        ->assertDispatched('toaster:received', settingsToast('error', 'voice:announcement-attestation'))
        ->assertSet('editing', '');

    expect((new RecordingAnnouncement(new DefaultsRegistry))->isAttested())->toBeTrue();
});

test('the screen still answers what the halt is set to, and names the screen that owns it', function (): void {
    // ⛔ **HIDING THESE ROWS WAS THE WORSE ANSWER AND THIS IS WHY.** 2402 is
    // explicit that the registry key **is** the switch and that no second row
    // anywhere answers "is the platform halted" — so an operator who comes here
    // to find out has to be told, and then told where the control is.
    (new DefaultsRegistry)->set(SendingGuard::OPERATOR_HALT_KEY, true, 'test');

    $html = html_entity_decode(
        Livewire::actingAs($this->admin)->test(SettingsScreen::class)->html(),
        ENT_QUOTES,
    );

    // ⚠️ **THE SENTENCES, NOT THE LABELS — 411's SHAPE, CAUGHT IN THIS SLICE.**
    // A first draft asserted `'Stop and start sending'` and
    // `route('admin.sending-controls')`, and both **pass with this whole panel
    // deleted**: the admin nav renders that label and that href on every screen.
    // `'php artisan voice:announcement-attestation record'` was worse — the
    // key's own manifest description contains it. What is unique to this panel
    // is the consequence sentence, so that is what is asserted, and the panel is
    // driven red by deleting it.
    foreach (OperatedElsewhere::all() as $key => $door) {
        expect($html)->toContain($key)
            ->and($html)->toContain($door['door'])
            ->and($html)->toContain($door['why']);
    }

    expect((new DefaultsRegistry)->value(SendingGuard::OPERATOR_HALT_KEY))->toBe(true);
});

test('searches keys and descriptions and says how many match', function (): void {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('search', 'competitor refresh costs')
        ->assertSee('places.tenant_daily_spend_ceiling_cents')
        ->assertSee('1 settings match')
        ->assertDontSee('routing.workload_window_days');
});

test('an empty search shows every group', function (): void {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('search', '')
        ->assertSee('AI')
        ->assertSee('Marketing')
        ->assertDontSee('settings match');
});

test('the group index links every group that has rows', function (): void {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->assertSee('href="#group-marketing"', escape: false)
        ->assertSee('Marketing (')
        ->assertSee('href="#group-billing"', escape: false)
        ->assertSee('Billing (');
});

test('an admin saves a list as a list, not as a string', function (): void {
    Livewire::actingAs($this->admin)->test(SettingsScreen::class)->call('edit', 'speed.script_deferral_extra_hosts')->assertSet('draft', '[]')->set('draft', '["cdn.distinctive-4471.example", "static.distinctive-4472.example"]')->call('save')->assertHasNoErrors();
    $registry = new DefaultsRegistry;
    expect($registry->value('speed.script_deferral_extra_hosts'))->toBeArray()->toBe(['cdn.distinctive-4471.example', 'static.distinctive-4472.example']);
    Livewire::actingAs($this->admin)->test(SettingsScreen::class)->call('edit', 'speed.script_deferral_extra_hosts')->assertSet('draft', '["cdn.distinctive-4471.example","static.distinctive-4472.example"]');
});

test('malformed or nested JSON stays a string rather than becoming a half-parsed value', function (): void {
    Livewire::actingAs($this->admin)->test(SettingsScreen::class)->call('edit', 'speed.script_deferral_extra_hosts')->set('draft', '[[1,2],[3]]')->call('save');
    $registry = new DefaultsRegistry;
    expect($registry->value('speed.script_deferral_extra_hosts'))->toBe('[[1,2],[3]]');

    Livewire::actingAs($this->admin)->test(SettingsScreen::class)->call('edit', 'speed.script_deferral_extra_hosts')->set('draft', '[not json')->call('save');
    expect($registry->value('speed.script_deferral_extra_hosts'))->toBe('[not json');
});
