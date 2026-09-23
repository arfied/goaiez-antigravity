<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\PollSupportMailboxJob;
use App\Models\MailInboxCursor;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\AccountDirectory;
use App\Services\Support\SupportInbox;
use App\Services\Support\SupportMailbox;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    /** @var TestCase $this */
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Cache::put('platform-mail:gmail-support:access-token', 'fake-token');
    config(['platform_mail.gmail.support.mailbox' => 'support@example.com']);
    config(['platform_mail.gmail.support.enabled' => true]);
});

afterEach(function () {
    Tenancy::forget();
    Cache::forget('platform-mail:gmail-support:access-token');
});

it('creates one ticket from a faked mailbox with one message', function () {
    MailInboxCursor::query()->forceCreate([
        'mailer' => 'gmail-support',
        'mailbox' => 'support@example.com',
        'history_id' => 'hist-0',
    ]);

    $domain = explode('@', $this->owner->email)[1];

    Http::fake([
        '*history*' => Http::response([
            'history' => [
                ['messagesAdded' => [['message' => ['id' => 'msg-1']]]],
            ],
            'historyId' => 'hist-2',
        ], 200),
        '*messages/msg-1*' => Http::response([
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => $this->owner->email],
                    ['name' => 'Subject', 'value' => 'Help'],
                    ['name' => 'Authentication-Results', 'value' => "mx.google.com; dkim=pass header.i=@{$domain}; spf=pass smtp.mailfrom={$this->owner->email}"],
                ],
                'body' => [
                    'data' => rtrim(strtr(base64_encode('I need help'), '+/', '-_'), '='),
                ],
            ],
            'internalDate' => (string) (now()->timestamp * 1000),
        ], 200),
    ]);

    $job = new PollSupportMailboxJob;
    $job->handle(app(SupportMailbox::class), app(AccountDirectory::class), app(SupportInbox::class));

    $message = SupportMessage::query()->first();
    expect($message)->not->toBeNull();
    expect($message->business_id)->toBe((int) $this->biz->id);
    expect($message->body)->toBe('I need help');
});

it('creates none from an empty mailbox', function () {
    MailInboxCursor::query()->forceCreate([
        'mailer' => 'gmail-support',
        'mailbox' => 'support@example.com',
        'history_id' => 'hist-0',
    ]);

    Http::fake([
        '*history*' => Http::response([
            'historyId' => 'hist-2',
        ], 200),
    ]);

    $job = new PollSupportMailboxJob;
    $job->handle(app(SupportMailbox::class), app(AccountDirectory::class), app(SupportInbox::class));

    expect(SupportMessage::query()->count())->toBe(0);
});

it('backoff returns the registry ladder', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'queue.backoff.standard_seconds'],
        ['value' => json_encode('5,10'), 'updated_at' => now()]
    );

    $job = new PollSupportMailboxJob;
    $backoff = $job->backoff();

    expect($backoff)->toHaveCount(2);
    expect($backoff[0])->toBeGreaterThanOrEqual(-25)->toBeLessThanOrEqual(35);
    expect($backoff[1])->toBeGreaterThanOrEqual(-20)->toBeLessThanOrEqual(40);
});
