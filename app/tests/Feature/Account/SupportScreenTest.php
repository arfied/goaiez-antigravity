<?php

declare(strict_types=1);

use App\Enums\SupportTicketStatus;
use App\Enums\UserRole;
use App\Livewire\Account\Support;
use App\Models\AuditLogEntry;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportDesk;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Mail::fake();
    Notification::fake();
});

test('real GET route renders', function () {
    $this->get(route('account.support'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console');
});

test('aborts when there is no tenant', function () {
    Tenancy::forgetAll();

    Livewire::test(Support::class)
        ->assertForbidden();

    $component = new Support;
    $desk = app(SupportDesk::class);

    $actions = ['raise', 'send', 'close'];

    foreach ($actions as $action) {
        $aborted = false;
        try {
            $component->$action($desk);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 403) {
                $aborted = true;
            }
        }
        expect($aborted)->toBeTrue();
    }
});

test('raises a ticket', function () {
    Livewire::test(Support::class)
        ->set('subject', 'hi')
        ->set('body', 'hi')
        ->call('raise')
        ->assertHasErrors(['subject', 'body'])
        ->set('subject', str_repeat('a', SupportDesk::SUBJECT_LIMIT + 1))
        ->set('body', str_repeat('a', SupportDesk::BODY_LIMIT + 1))
        ->call('raise')
        ->assertHasErrors(['subject', 'body']);

    Livewire::test(Support::class)
        ->set('subject', 'Valid subject')
        ->set('body', 'Valid body text')
        ->call('raise')
        ->assertHasNoErrors()
        ->assertSet('subject', '')
        ->assertSet('body', '')
        ->assertSet('openTicketId', function ($value) {
            return $value !== null;
        });

    $ticket = SupportTicket::first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->subject)->toBe('Valid subject');

    $audit = AuditLogEntry::where('action', 'support.ticket_raised')->first();
    expect($audit)->not->toBeNull();
});

test('open and back actions', function () {
    Livewire::test(Support::class)
        ->set('openTicketId', null)
        ->set('reply', 'some text')
        ->call('open', 99)
        ->assertSet('openTicketId', 99)
        ->assertSet('reply', '')
        ->set('reply', 'more text')
        ->call('back')
        ->assertSet('openTicketId', null)
        ->assertSet('reply', '');
});

test('sends a reply', function () {
    $component = Livewire::test(Support::class)
        ->set('openTicketId', null)
        ->call('send')
        ->assertHasNoErrors();

    expect(SupportMessage::count())->toBe(0);

    $desk = app(SupportDesk::class);
    $ticket = $desk->raise($this->owner, 'Subject', 'Body');

    $component = Livewire::test(Support::class)
        ->set('openTicketId', $ticket->id)
        ->set('reply', 'a')
        ->call('send')
        ->assertHasErrors(['reply'])
        ->set('reply', str_repeat('a', SupportDesk::BODY_LIMIT + 1))
        ->call('send')
        ->assertHasErrors(['reply']);

    $component->set('reply', 'Valid reply')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('reply', '');

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicketStatus::Open)
        ->and(SupportMessage::count())->toBe(2);

    $audit = AuditLogEntry::where('action', 'support.ticket_replied')->first();
    expect($audit)->not->toBeNull();

    $desk->resolveAsTenant($ticket->id, $this->owner);
    $component->set('reply', 'More reply')
        ->call('send')
        ->assertHasErrors(['reply' => 'That request is closed. Raising a new one keeps the history readable.']);

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    $bizB = TestCase::provisionTenant(['owner_user_id' => $ownerB->id]);
    Tenancy::setUser($ownerB->id);
    Tenancy::set((int) $bizB->id);

    $ticketB = $desk->raise($ownerB, 'Sub', 'Bod');

    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Livewire::test(Support::class)
        ->set('openTicketId', $ticketB->id)
        ->set('reply', 'reply to B')
        ->call('send')
        ->assertHasErrors(['reply' => 'That request no longer exists.']);
});

test('closes a ticket', function () {
    Livewire::test(Support::class)
        ->set('openTicketId', null)
        ->call('close')
        ->assertHasNoErrors();

    $desk = app(SupportDesk::class);
    $ticket = $desk->raise($this->owner, 'Sub', 'Bod');

    Livewire::test(Support::class)
        ->set('openTicketId', $ticket->id)
        ->call('close')
        ->assertHasNoErrors();

    $ticket->refresh();
    expect($ticket->status->isLive())->toBeFalse();

    $audit = AuditLogEntry::where('action', 'support.ticket_resolved')->first();
    expect($audit)->not->toBeNull();

    Livewire::test(Support::class)
        ->set('openTicketId', $ticket->id)
        ->call('close')
        ->assertHasErrors(['reply' => 'That request is closed. Raising a new one keeps the history readable.']);
});

test('the close toast does not promise a reopen the desk refuses', function () {
    $desk = app(SupportDesk::class);
    $ticket = $desk->raise($this->owner, 'To close', 'Closing this soon');

    Livewire::test(Support::class)
        ->call('open', $ticket->id)
        ->call('close')
        ->assertDispatched('toaster:received', function (string $name, array $params): bool {
            return str_contains($params['message'] ?? '', 'ask a new question')
                && ! str_contains($params['message'] ?? '', 'replying opens it again');
        });

    expect(fn () => $desk->replyAsTenant($ticket->id, $this->owner, 'More reply'))
        ->toThrow(InvalidArgumentException::class, 'That request is closed.');
});
