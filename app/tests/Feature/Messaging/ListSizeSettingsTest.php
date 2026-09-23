<?php

use App\Enums\OutreachChannel;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\OutreachMessage;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Conversations\ConversationThreads;
use App\Services\Messaging\MessageLog;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->location = $this->biz->locations()->first();
    $this->location->forceFill([
        'website_url' => 'https://example.test',
        'website_confirmed_at' => now(),
    ])->save();
    Mail::fake();
    Notification::fake();
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('limits message log page size', function () {
    PlatformSetting::write('messaging.log.per_page', 5, 'test');
    OutreachMessage::factory()->count(10)->create(['customer_id' => null]);
    expect(app(MessageLog::class)->page()->items())->toHaveCount(5);
});

it('limits conversations limit', function () {
    PlatformSetting::write('conversations.list_limit', 5, 'test');
    Conversation::factory()->count(10)->create(['channel' => OutreachChannel::Sms]);
    expect(app(ConversationThreads::class)->list())->toHaveCount(5);
});
