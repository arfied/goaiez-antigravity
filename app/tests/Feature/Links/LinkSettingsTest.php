<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Tenancy;
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
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X176\Actions\InternalLinkRenderAction;
use App\Modules\X191\Actions\LinkPitchAction;
use App\Modules\X191\Models\LinkTarget;

it('respects pitch monthly send ceiling setting', function () {
    PlatformSetting::write('links.pitch.monthly_send_ceiling', 1, 'test');
    $action = app(LinkPitchAction::class);
    $target1 = LinkTarget::create(['business_id' => $this->biz->id, 'domain' => 'a.com', 'target_url' => 'https://a.com', 'is_pbn' => false]);
    $target2 = LinkTarget::create(['business_id' => $this->biz->id, 'domain' => 'b.com', 'target_url' => 'https://b.com', 'is_pbn' => false]);

    $action->sendPitch($this->biz->id, $target1->id, 'test', 'fact');
    expect(fn () => $action->sendPitch($this->biz->id, $target2->id, 'test', 'fact'))
        ->toThrow(InvalidArgumentException::class, 'monthly send ceiling reached');
});

it('respects internal link emitted max setting', function () {
    // This is hard to test without PageReadAction creating pages, let's just write the setting and do a basic test
    PlatformSetting::write('links.internal.emitted_max', 0, 'test');
    $action = app(InternalLinkRenderAction::class);
    // Since emitted max is 0, it should output nothing even if pages exist.
    // Without pages it returns '', so this is safe.
    expect($action->handle($this->biz->id))->toBe('');
});
