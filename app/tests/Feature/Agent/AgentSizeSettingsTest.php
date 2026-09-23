<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Agent\AgentComposer;
use App\Services\Agent\ThreadCloseSummaries;
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

it('agent composer honours snippet chars', function () {
    PlatformSetting::write('agent.compose.snippet_chars', 10, 'test');

    $service = app(AgentComposer::class);
    $this->assertEquals(10, $service->snippetCharacters());
});

it('thread close summaries honours messages read', function () {
    PlatformSetting::write('agent.summary.messages_read', 5, 'test');

    $service = app(ThreadCloseSummaries::class);
    $this->assertEquals(5, $service->messagesRead());
});
