<?php

declare(strict_types=1);

use App\Enums\OwnerNotificationKind;
use App\Livewire\Admin\OwnerChannelTexts;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\OwnerNotification;
use App\Models\OwnerReply;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Consent\OwnerConsentService;
use App\Services\Sms\OwnerNotifications;
use App\Services\Support\AccountDirectory;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Texts with an account holder — wave 41 lane E, decision 11110
|--------------------------------------------------------------------------
|
| ⛔ **THE READER FOUR COLUMNS WERE WRITTEN FOR AND DID NOT HAVE.**
| `owner_notifications.kind`, `.occasion` and `.provider_message_id`, and
| `owner_replies.in_reply_to_notification_id`, had writers and no reader
| anywhere in `app/` — 272's shape, four columns wide. 10843 named this screen,
| said it was cheap, and could not build it because another lane owned
| `app/Livewire/Admin/` that wave. The sentence it said the screen would render
| is the specification: *"we texted them about X at T; they said Y at T+4m."*
|
| ⛔ **MOST OF THIS FILE IS ABOUT WHAT THE SCREEN MUST NOT LET A READER
| INVENT**, which is the part a rendering test would otherwise skip:
|
|   1. A row is a SEND, never a DELIVERY (10823, 9371).
|   2. The link to a reply is an INFERENCE FROM RECENCY (10829), and null —
|      *"we do not know what this answers"* — is the common case.
|   3. NOTHING acts on a reply (10722, unchanged by this screen).
|
| ⚠️ **THE ACTION'S OWN GUARD IS PROVEN BY CALLING IT DIRECTLY.** A Livewire
| authorization test driven through the harness proves `render()`'s guard and
| never the action's own — measured in wave 40: the action's `authorize()`
| deleted, the harness test stayed green at one assertion. The isolating test
| below instantiates the component and calls the method with no render after it.
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);

    $this->admin = User::factory()->create(['name' => 'Platform Staff']);

    Tenancy::forgetAll();

    $this->business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Ledger Plumbing',
    ]);

    Tenancy::forgetAll();
});

/** One recorded owner-directed send, inside the tenant that owns it. */
function ownerTextSent(Business $business, ?string $sentAt = null): OwnerNotification
{
    $sent = Tenancy::actingAs((int) $business->id, fn (): OwnerNotification => OwnerNotification::factory()->create([
        'occasion' => 'mo-urgent-1',
        'provider_message_id' => 'mt-carrier-9001',
        'sent_at' => $sentAt ?? now()->subHour(),
    ]));

    Tenancy::forgetAll();

    return $sent;
}

/** One stored reply, optionally naming the send it appears to answer. */
function ownerTextReply(Business $business, string $body, ?OwnerNotification $answers = null): OwnerReply
{
    $reply = Tenancy::actingAs((int) $business->id, fn (): OwnerReply => OwnerReply::factory()->create([
        'body' => $body,
        'in_reply_to_notification_id' => $answers?->getKey(),
        'received_at' => now(),
    ]));

    Tenancy::forgetAll();

    return $reply;
}

/*
|--------------------------------------------------------------------------
| The sentence 10843 asked for
|--------------------------------------------------------------------------
*/

test('the screen renders what we texted them and what they said back, newest first', function (): void {
    // ⛔ **THE WHOLE SPECIFICATION IN ONE TEST** — *"we texted them about X at
    // T; they said Y at T+4m."*
    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'On my way now, thanks.', $sent);

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSeeInOrder([
            'They replied',
            'On my way now, thanks.',
            'We sent',
            OwnerNotificationKind::UrgentEscalation->label(),
        ])
        // ⛔ **THE STUTTER READING THE RENDERED PAGE AS TEXT FOUND.** Every
        // reply row said *"They replied — <time>"* and then *"They texted
        // back"*, so the account holder's own words were the third thing on the
        // row. No assertion in this codebase could see it; `innerText` could.
        ->assertDontSee('They texted back');
});

test('the four columns that had no reader are all on the page', function (): void {
    // ⛔ **272's SHAPE, FOUR COLUMNS WIDE, CLOSED.** `kind` (as a sentence),
    // `occasion`, `provider_message_id` and `in_reply_to_notification_id` (as
    // the answering sentence) each have exactly one reader now and it is this.
    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'Understood.', $sent);

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        // kind, through `OwnerNotificationKind::label()` — the enum's own
        // sentence rather than a second one on the screen (11117)
        ->assertSee(OwnerNotificationKind::UrgentEscalation->label())
        // occasion
        ->assertSee('mo-urgent-1')
        // provider_message_id, labelled — an unlabelled string on a staff
        // screen is noise, which reading the rendered page as text made obvious
        ->assertSee('own reference for the message')
        ->assertSee('mt-carrier-9001')
        // in_reply_to_notification_id
        ->assertSee('It arrived soon after the message we sent on');
});

test('a reply that answers nothing says we do not know, never that it is not a reply', function (): void {
    // ⚠️ **NULL IS THE COMMON ANSWER** (10829). Every owner who says something
    // unprompted lands here, and a screen that rendered the null as *"not a
    // reply"* would be inventing a fact about what they meant.
    ownerTextReply($this->business, 'Are you still open on Sundays?');

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('We do not know what this answers')
        ->assertSee('or this arrived before we kept this record')
        ->assertDontSee('It arrived soon after the message we sent on');
});

test('a reply naming a send older than the page shows says so rather than saying we do not know', function (): void {
    // ⚠️ **TWO DIFFERENT IGNORANCES, AND COLLAPSING THEM WOULD LET AN OPERATOR
    // REPORT THE WRONG ONE.** *"Nothing recent enough had been sent"* and *"the
    // message it answers is older than this page reaches"* are different facts.
    //
    // ⚠️ **THE ONLY WAY TO REACH THIS ARM IS THE PAGE CAP, WHICH IS WHY THE
    // FIXTURE IS SO LARGE.** `in_reply_to_notification_id` is a real foreign key
    // with `nullOnDelete()`, so a pruned send leaves the reply's column NULL
    // rather than dangling — there is no such thing here as an id naming no
    // row. The honest fixture is one send past `LEDGER_LIMIT`.
    $oldest = ownerTextSent($this->business, now()->subDays(30)->toDateTimeString());

    Tenancy::actingAs((int) $this->business->id, function (): void {
        OwnerNotification::factory()->count(OwnerNotifications::LEDGER_LIMIT)->create();
    });

    Tenancy::forgetAll();

    ownerTextReply($this->business, 'Fine by me.', $oldest);

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('older than the ones listed here')
        ->assertDontSee('We do not know what this answers');
});

test('a business with nothing either way says so rather than leaving a blank', function (): void {
    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Nothing has been said either way');
});

/*
|--------------------------------------------------------------------------
| What the page must not be read as saying
|--------------------------------------------------------------------------
*/

test('the page says a send is not a delivery, before anything is looked up', function (): void {
    // ⛔ **9371's SHAPE ON A SCREEN.** A row records a dispatch and an operator
    // reading it as a delivery would tell somebody their account holder got a
    // message nothing here can prove arrived. It is on the page BEFORE a
    // lookup, because a boundary an operator meets only after the answer is a
    // boundary they have already crossed.
    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->assertSee('What this page cannot tell you')
        ->assertSee('That is not proof a phone received it')
        ->assertSee('A reply is matched to a message by timing alone')
        ->assertSee('Nothing reads these replies');
});

test('the correlation window on the page is the constant, not a number somebody typed', function (): void {
    // ⚠️ A hand-typed "72 hours" is a sentence that goes wrong the day the
    // constant moves, in the one paragraph telling an operator how much to
    // trust the link.
    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->assertSee('Within '.OwnerNotifications::CORRELATION_WINDOW_HOURS.' hours');
});

test('the page offers no way to text them back, and no way to change anything', function (): void {
    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'Understood.', $sent);

    $component = Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp');

    // ⚠️ **`assertDontSee` PASSES VACUOUSLY WHEN ITS LOCATOR MATCHES NOTHING**,
    // so this is paired with the positive assertions above on the same render:
    // the page demonstrably has content, and none of it is an action.
    $component->assertDontSee('wire:click')
        ->assertDontSee('Resend')
        ->assertDontSee('Text them back');
});

/*
|--------------------------------------------------------------------------
| The tenant boundary, the lock and the trace
|--------------------------------------------------------------------------
*/

test('one tenant\'s account holder never appears on another tenant\'s page', function (): void {
    $other = Business::provision(['owner_user_id' => User::factory()->create()->id, 'name' => 'Sunrise Cafe']);

    Tenancy::forgetAll();

    ownerTextReply($this->business, 'Ours says this.');
    ownerTextReply($other, 'Theirs says that.');

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Ours says this.')
        ->assertDontSee('Theirs says that.');
});

test('the business in view cannot be set from the client', function (): void {
    // ⛔ **THE LOCK IS WHAT MAKES THE AUDIT UNSKIPPABLE.** Without it a client
    // could set `businessId` directly and have `render()` read an account
    // holder's own messages with `lookUp()` never called — so with nothing
    // written to any tenant's log.
    ownerTextReply($this->business, 'Private.');

    expect(fn (): mixed => Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('businessId', $this->business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('a resolved lookup is recorded in the looked-up tenant\'s own log', function (): void {
    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
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
    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', '999999')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null);

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(1);
    });
});

test('the account holder\'s own mobile number is nowhere on this page', function (): void {
    // ⚠️ **TIEBREAKER (2), ON A SCREEN RATHER THAN IN A SCHEMA.** This page
    // answers *"what was said"*, and the number it was said to is a different
    // question with its own screen and its own audit entry
    // (`admin.owner-notify-consents`). Rendering it here would put an account
    // holder's mobile on a second surface for nothing.
    //
    // ⚠️ **NOT A VACUOUS `assertDontSee`** — the same render is asserted to
    // contain the reply, so the locator demonstrably matches something.
    Tenancy::actingAs((int) $this->business->id, fn (): mixed => app(OwnerConsentService::class)->capture(
        '+15551239876',
        [
            'url' => 'https://example.test/account/settings',
            'ip_hash' => str_repeat('d', 64),
            'user_agent' => 'Mozilla/5.0 (test)',
        ],
        'user:1',
        consentChecked: true,
    ));

    Tenancy::forgetAll();

    ownerTextReply($this->business, 'All sorted.');

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('All sorted.')
        ->assertDontSee('+15551239876')
        ->assertDontSee('15551239876');
});

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
*/

test('the screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->assertForbidden();
});

test('the lookup action carries its own gate, proven without a render behind it', function (): void {
    // ⛔ **THE ONLY TEST IN THIS FILE THAT PROVES THE ACTION'S OWN GUARD.**
    // Measured in wave 40 across this console: delete the action's
    // `authorize()` and every harness-driven authorization test stays green at
    // one assertion, because `render()` runs after every action and refuses
    // first. This calls the method directly, with nothing after it.
    //
    // MUTATION (observed): removing `$this->authorize(AdminAccess::GATE);` from
    // `OwnerChannelTexts::lookUp()` reddens this and nothing else in this file.
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $this->actingAs($this->admin);

    $component = new OwnerChannelTexts;
    $component->lookup = (string) $this->business->id;

    expect(fn (): mixed => $component->lookUp(app(AuditService::class)))
        ->toThrow(AuthorizationException::class);
});

test('the route is behind the admin gate too, because hiding a nav item is not authorization', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $this->actingAs($this->admin)
        ->get(route('admin.owner-channel-texts'))
        ->assertForbidden();
});

test('an admin reaches the screen from the console nav', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.owner-channel-texts'))
        ->assertOk()
        ->assertSee('Texts with an account holder');
});

/*
|--------------------------------------------------------------------------
| The service the screen reads through
|--------------------------------------------------------------------------
*/

test('the ledger reads through the one file permitted to touch either table', function (): void {
    // ⛔ **THE ANTI-VACUITY FLOOR FOR 11111'S NEW ENTRY ON `OwnerChannelTest`'s
    // PERMITTED LIST, AND IT NAMES THE READER'S FILE RATHER THAN THE LIST**
    // (9020-9039). A floor keyed on the list asks *"does every permitted entry
    // still read?"*, which is satisfied by deleting the entry — so it would
    // pass over an application where the screen had quietly grown its own
    // query. This asserts the read is where the argument says it is.
    // ⚠️ **`str_contains()` INTO A BOOLEAN RATHER THAN `toContain()` ON THE
    // SOURCE.** A failing `toContain()` prints the whole 14 KB file as the
    // diff, which buries the one sentence a reader needs.
    $service = (string) file_get_contents(app_path('Services/Sms/OwnerNotifications.php'));
    $screen = (string) file_get_contents(app_path('Livewire/Admin/OwnerChannelTexts.php'));

    expect(str_contains($service, 'OwnerReply::query()'))
        ->toBeTrue('OwnerNotifications no longer reads owner_replies — its entry on OwnerChannelTest\'s permitted list now protects nothing')
        ->and(str_contains($screen, 'OwnerReply'))
        ->toBeFalse('The screen names OwnerReply directly, which needs a SECOND entry on that list — read 11111 before adding one')
        ->and(str_contains($screen, 'ledgerFor('))
        ->toBeTrue('The screen no longer reads through the service, so the chokepoint argument at 11111 is false');
});

test('the ledger answers inside the tenant and never leaks another one', function (): void {
    $other = Business::provision(['owner_user_id' => User::factory()->create()->id, 'name' => 'Sunrise Cafe']);

    Tenancy::forgetAll();

    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'Mine.', $sent);
    ownerTextReply($other, 'Theirs.');

    $ledger = app(OwnerNotifications::class)->ledgerFor((int) $this->business->id);

    expect($ledger['sends'])->toHaveCount(1)
        ->and($ledger['sends'][0]['kind'])->toBe(OwnerNotificationKind::UrgentEscalation)
        ->and($ledger['replies'])->toHaveCount(1)
        ->and($ledger['replies'][0]['body'])->toBe('Mine.')
        ->and($ledger['replies'][0]['answering'])->toBe((int) $sent->getKey());
});

test('the ledger is capped, and the screen says so rather than implying it is complete', function (): void {
    // ⚠️ Nothing bounds either table on any deployment that exists —
    // `owner_channel.retention_days` ships with no seed and an unset period is
    // a no-op (10834) — so an uncapped read is a page that gets slower for ever.
    Tenancy::actingAs((int) $this->business->id, function (): void {
        OwnerReply::factory()->count(3)->create();
    });

    Tenancy::forgetAll();

    expect(app(OwnerNotifications::class)->ledgerFor((int) $this->business->id, limit: 2)['replies'])
        ->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| What the audit entry can and cannot say — wave 42 lane B, 11180-11182
|--------------------------------------------------------------------------
|
| ⛔ THE READ IS AUDITED AND THE LOG COULD NOT SAY WHAT WAS READ. Eleven
| surfaces file `business.viewed_by_staff` and this is the first whose subject is
| a named person's own message bodies — indistinguishable, in the log, from
| somebody opening the review queue.
|
| ⛔ THE REMEDY IS NOT A NEW ACTION STRING AND THE ARGUMENT IS AT THE CALL SITE.
| A per-screen action would make the most sensitive of the eleven invisible to
| the query that exists to find reads. What was missing is the `surface` key
| `AccountDirectory::open()` has filed since 622.
|
*/

test('the recorded read names which surface was opened, and that is the only thing it adds', function (): void {
    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'Ring me on the mobile, it is 07700 900123.', $sent);

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        // ⛔ THE ACTION STRING IS UNCHANGED, WHICH IS HALF THE FINDING. A staff
        // read of this screen still answers the same query as a staff read of
        // any other account surface (622).
        expect($entry->action)->toBe('business.viewed_by_staff')
            ->and($entry->metadata)->toBe(['surface' => OwnerChannelTexts::AUDIT_SURFACE]);

        // ⛔ AND NOTHING ELSE GOES IN. `audit_log` is append-only forever and
        // nothing prunes it, so the entry records WHICH SCREEN was opened and
        // never WHAT WAS ON IT — `AccountDirectory`'s own rule, which records
        // how an account was matched rather than what was typed.
        expect(json_encode($entry->metadata))->not->toContain('Ring me on the mobile')
            ->and(json_encode($entry->metadata))->not->toContain('900123')
            ->and(json_encode($entry->metadata))->not->toContain('Ledger Plumbing');
    });
});

test('this screen files the same action string as every other staff surface, deliberately', function (): void {
    // ⛔ THE ANTI-VACUITY ARM FOR THE SENTENCE ABOVE. Asserting only that this
    // screen writes `business.viewed_by_staff` is satisfied by a tree in which
    // it is the sole user of the string — so the shared-ness is what is
    // asserted, by driving a SECOND surface and finding both under one query.
    //
    // ⚠️ `AccountDirectory` is the sibling with the precedent, and it is not
    // this screen, not a Livewire component and not in `app/Livewire/Admin`, so
    // it cannot pass by accident.
    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'Fine by me.', $sent);

    Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertHasNoErrors();

    app(AccountDirectory::class)
        ->open((string) $this->business->id, 'user:'.$this->admin->id);

    Tenancy::actingAs($this->business->id, function (): void {
        $surfaces = AuditLogEntry::query()
            ->where('action', 'business.viewed_by_staff')
            ->pluck('metadata')
            ->map(fn (mixed $m): mixed => is_array($m) ? ($m['surface'] ?? null) : null)
            ->sort()
            ->values()
            ->all();

        // Two reads of one account, two surfaces, ONE action string — which is
        // exactly what a per-screen taxonomy would have destroyed.
        expect($surfaces)->toBe(['account_360', OwnerChannelTexts::AUDIT_SURFACE]);
    });
});

test('a re-render of the same lookup files no second entry, and that refusal is quoted rather than new', function (): void {
    // ⛔ REFUSED IN WRITING BEFORE THIS SCREEN EXISTED, at
    // `AccountDirectory::open()`: *"ONE ENTRY PER RESOLVED LOOKUP, NOT PER
    // RENDER. Livewire re-renders on every property update, so auditing the read
    // path would file a row per keystroke and make the log unreadable, which is
    // its own kind of unaudited."* ⛔ AND `audit_log` IS APPEND-ONLY AND NOTHING
    // PRUNES IT, so a row per round trip is an unbounded write with no horizon.
    //
    // ⚠️ NOT A VACUOUS ASSERTION THAT NOTHING HAPPENED — the ledger is asserted
    // to be re-read on each refresh, so the count of one is a count taken while
    // the words really were being read again.
    $sent = ownerTextSent($this->business);
    ownerTextReply($this->business, 'Second look at this.', $sent);

    $component = Livewire::actingAs($this->admin)
        ->test(OwnerChannelTexts::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Second look at this.');

    $component->call('$refresh')->assertSee('Second look at this.');
    $component->call('$refresh')->assertSee('Second look at this.');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(1);
    });
});
