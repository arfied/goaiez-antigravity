<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\MagicLinkToken;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\MagicLinkService;
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

it('magic link honours lifetime minutes', function () {
    PlatformSetting::write('auth.magic_link.lifetime_minutes', 30, 'test');

    $service = app(MagicLinkService::class);
    $service->request($this->owner->email, 'ip');

    $token = MagicLinkToken::first();
    $this->assertNotNull($token);
    $this->assertEquals(30, (int) round(now()->diffInMinutes($token->expires_at, true)));
});
