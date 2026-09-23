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

use App\Modules\CBilling\Domain\BillingLedgerEngine;

it('respects daily topup ceiling setting', function () {
    PlatformSetting::write('billing.topup.daily_ceiling_cents', 10000, 'test');
    $engine = app(BillingLedgerEngine::class);

    $engine->topup($this->biz->id, 10000); // Should succeed

    expect(fn () => $engine->topup($this->biz->id, 1))
        ->toThrow(DomainException::class, 'REFUSAL: Daily top-up ceiling exceeded');
});

it('respects voicemail only day setting', function () {
    PlatformSetting::write('billing.cycle.voicemail_only_from_day', 15, 'test');
    $engine = app(BillingLedgerEngine::class);

    $state1 = $engine->advanceDunning($this->biz->id, 14);
    expect($state1->voicemail_only)->toBeFalse();

    $state2 = $engine->advanceDunning($this->biz->id, 15);
    expect($state2->voicemail_only)->toBeTrue();
});
