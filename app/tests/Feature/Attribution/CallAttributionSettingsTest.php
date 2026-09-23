<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X137\Actions\CallAttributeAction;
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

it('a call attribution written as 15 minutes expires at 15 minutes', function () {
    PlatformSetting::write('attribution.call.ttl_minutes', 15, 'test');

    $action = app(CallAttributeAction::class);
    $token = $action->allocateToken($this->biz->id, 'session_123', '+15551234567');

    expect($token->expires_at->toDateTimeString())->toBe(now()->addMinutes(15)->toDateTimeString());
});
