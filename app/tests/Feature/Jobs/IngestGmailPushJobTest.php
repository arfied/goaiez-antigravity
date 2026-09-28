<?php

declare(strict_types=1);

use App\Enums\OutreachStatus;
use App\Enums\UserRole;
use App\Jobs\IngestGmailPushJob;
use App\Models\MailInboxCursor;
use App\Models\MailTrackingCode;
use App\Models\OutreachMessage;
use App\Models\User;
use App\Services\Mail\GmailInbox;
use App\Services\Mail\MailReplyRouter;
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

    Cache::put('platform-mail:gmail:access-token', 'fake-token');
    config(['platform_mail.gmail.inbox.enabled' => true]);
});

afterEach(function () {
    Tenancy::forget();
    Cache::forget('platform-mail:gmail:access-token');
});

it('routes a faked inbox message and writes the row', function () {
    MailInboxCursor::query()->forceCreate([
        'mailer' => 'gmail',
        'mailbox' => 'me',
        'history_id' => 'hist-0',
    ]);

    $outreach = OutreachMessage::factory()->create([
        'business_id' => $this->biz->id,
        'status' => OutreachStatus::Sent,
    ]);

    MailTrackingCode::factory()->create([
        'business_id' => $this->biz->id,
        'outreach_message_id' => $outreach->id,
        'code' => 'XYZ23456',
    ]);

    Http::fake([
        '*users/me/history*' => Http::response([
            'history' => [
                ['messagesAdded' => [['message' => ['id' => 'msg-1']]]],
            ],
            'historyId' => 'hist-2',
        ], 200),
        '*messages/msg-1*' => Http::response([
            'payload' => [
                'headers' => [
                    ['name' => 'To', 'value' => 'reply+XYZ23456@test.com'],
                ],
            ],
        ], 200),
    ]);

    $job = new IngestGmailPushJob('me', 'hist-1');
    $job->handle(app(GmailInbox::class), app(MailReplyRouter::class));

    $outreach->refresh();
    expect($outreach->status->value)->toBe(OutreachStatus::Replied->value);
});

it('routes nothing when inbox is empty', function () {
    MailInboxCursor::query()->forceCreate([
        'mailer' => 'gmail',
        'mailbox' => 'me',
        'history_id' => 'hist-0',
    ]);

    $outreach = OutreachMessage::factory()->create([
        'business_id' => $this->biz->id,
        'status' => OutreachStatus::Sent,
    ]);

    Http::fake([
        '*users/me/history*' => Http::response([
            'historyId' => 'hist-2',
        ], 200),
    ]);

    $job = new IngestGmailPushJob('me', 'hist-1');
    $job->handle(app(GmailInbox::class), app(MailReplyRouter::class));

    $outreach->refresh();
    expect($outreach->status->value)->toBe(OutreachStatus::Sent->value);
});

it('backoff returns the registry ladder', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'queue.backoff.standard_seconds'],
        ['value' => json_encode('5,10'), 'updated_at' => now()]
    );

    $job = new IngestGmailPushJob('me', 'hist-1');
    $backoff = $job->backoff();

    expect($backoff)->toHaveCount(2);
    expect($backoff[0])->toBeGreaterThanOrEqual(-25)->toBeLessThanOrEqual(35);
    expect($backoff[1])->toBeGreaterThanOrEqual(-20)->toBeLessThanOrEqual(40);
});
