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

use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X139\Domain\ConversionUploadEngine;

it('respects conversion window days setting', function () {
    PlatformSetting::write('attribution.conversion.window_days', 30, 'test');
    $engine = app(ConversionUploadEngine::class);
    $res = $engine->upload($this->biz->id, 1, 1000, now()->subDays(31));
    expect($res['status'])->toBe('rejected');
});

it('respects signal high intent score setting', function () {
    PlatformSetting::write('signals.high_intent_score', 90.0, 'test');
    $action = app(SignalScoreAction::class);
    $score = $action->recordAndScore($this->biz->id, 'test', 'visit', [], 80.0);
    expect($score->is_high_intent)->toBeFalse();
});
