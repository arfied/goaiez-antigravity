<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GbpAccountBinding;
use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Models\ZernioAccountDay;
use App\Services\Gbp\ZernioSpend;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('meters every connected account across both tables', function () {
    $spend = app(ZernioSpend::class);

    GbpProfileBinding::create([
        'business_id' => $this->biz->id,
        'profile_ref' => 'profile_8366',
    ]);

    // (a) One Google binding plus one Facebook, one Instagram and one WhatsApp ZernioAccountBinding
    GbpAccountBinding::create([
        'account_ref' => 'acct_8361',
        'business_id' => $this->biz->id,
        'location_id' => 8361,
    ]);
    ZernioAccountBinding::create(['account_ref' => 'acct_8362', 'profile_ref' => 'prof_8362', 'platform' => 'facebook']);
    ZernioAccountBinding::create(['account_ref' => 'acct_8363', 'profile_ref' => 'prof_8363', 'platform' => 'instagram']);
    ZernioAccountBinding::create(['account_ref' => 'acct_8364', 'profile_ref' => 'profile_8366', 'platform' => 'whatsapp']);

    expect($spend->recordAccountDaysToday())->toBe(4);
    expect($spend->accountDaysInMonth())->toBe(4);

    // (b) A second call the same day returns 0, and the month count stays 4
    expect($spend->recordAccountDaysToday())->toBe(0);
    expect($spend->accountDaysInMonth())->toBe(4);

    // (c) The WhatsApp binding's day row carries the business_id mapped from its profile_ref
    $waDay = ZernioAccountDay::where('account_ref', 'acct_8364')->first();
    expect($waDay->business_id)->toBe($this->biz->id);

    // A binding whose profile maps to no business gets a null business_id
    $fbDay = ZernioAccountDay::where('account_ref', 'acct_8362')->first();
    expect($fbDay->business_id)->toBeNull();

    // (d) The same account_ref present in BOTH tables is counted once
    GbpAccountBinding::create([
        'account_ref' => 'acct_8365',
        'business_id' => $this->biz->id,
        'location_id' => 8365,
    ]);
    ZernioAccountBinding::create(['account_ref' => 'acct_8365', 'profile_ref' => 'prof_8365', 'platform' => 'whatsapp']);

    expect($spend->recordAccountDaysToday())->toBe(1);
    expect($spend->accountDaysInMonth())->toBe(5);
});
