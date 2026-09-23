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

use App\Modules\CMail\Actions\EmailWarmupAction;
use App\Modules\CMail\Models\MailDomain;

it('respects warmup jitter pct setting', function () {
    PlatformSetting::write('mail.warmup.jitter_pct', 0, 'test');
    $action = app(EmailWarmupAction::class);
    $domain = MailDomain::create(['business_id' => $this->biz->id, 'domain_name' => 'test.com']);
    $cal = $action->handle($this->biz->id, $domain->id);
    expect($cal->schedule['day_1']['min'])->toBe(50)
        ->and($cal->schedule['day_1']['max'])->toBe(50);
});

it('respects warmup daily allowance setting', function () {
    PlatformSetting::write('mail.warmup.daily_allowance', 200, 'test');
    $action = app(EmailWarmupAction::class);
    $domain = MailDomain::create(['business_id' => $this->biz->id, 'domain_name' => 'test.com']);
    $cal = $action->handle($this->biz->id, $domain->id);
    expect($cal->daily_allowance)->toBe(200);
});
