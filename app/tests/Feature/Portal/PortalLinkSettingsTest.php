<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X172\Actions\PortalLinkAction;
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

it('a portal link written as 12 hours expires at 12 hours', function () {
    PlatformSetting::write('portal.link.ttl_hours', 12, 'test');

    $action = app(PortalLinkAction::class);
    $link = $action->handle($this->biz->id, 'resource', 1);

    expect($link->expires_at->toDateTimeString())->toBe(now()->addHours(12)->toDateTimeString());
});
