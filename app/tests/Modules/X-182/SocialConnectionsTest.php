<?php

declare(strict_types=1);

use App\Exceptions\ZernioConnectionRefused;
use App\Models\Location;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Domain\SocialConnections;
use App\Modules\X182\Models\SocialAccount;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant(['name' => 'Social Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');

    Http::fake([
        'zernio.com/api/v1/profiles*' => Http::response(['profiles' => [['_id' => 'profile_6001']]]),
        'zernio.com/api/v1/connect/facebook*' => Http::response(['authUrl' => 'https://zernio.com/auth/fb-6002']),
        'zernio.com/api/v1/connect/instagram*' => Http::response(['authUrl' => 'https://zernio.com/auth/ig-6002']),
        'zernio.com/api/v1/accounts*facebook*' => Http::response(['accounts' => [['_id' => 'acct_fb_6003', 'profileId' => 'profile_6001', 'platform' => 'facebook']]]),
    ]);
});

test('d begin creates pending row and returns authUrl', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $loc = Location::create(['business_id' => $this->biz->id, 'name' => 'Loc1']);

    $connections = app(SocialConnections::class);
    $url = $connections->begin('facebook', $loc->id, fn() => 'https://app.test/cb', 'user:1');

    expect($url)->toBe('https://zernio.com/auth/fb-6002');

    $count = SocialAccount::where('business_id', $this->biz->id)->count();
    expect($count)->toBe(1);

    $row = SocialAccount::first();
    expect($row->status)->toBe('pending');
    expect($row->provider_profile_ref)->toBe('profile_6001');
    expect($row->location_id)->toBe($loc->id);

    // A second begin updates the same row
    $connections->begin('facebook', $loc->id, fn() => 'https://app.test/cb2', 'user:1');
    $count = SocialAccount::where('business_id', $this->biz->id)->count();
    expect($count)->toBe(1);

    // foreign location -> refused
    $biz2 = TestCase::provisionTenant(['name' => 'Biz2', 'currency' => 'USD']);
    Tenancy::set($biz2->id);
    $loc2 = Location::create(['business_id' => $biz2->id, 'name' => 'Loc2']);
    Tenancy::set($this->biz->id); // Back to biz1

    $thrown = false;
    try {
        $connections->begin('facebook', $loc2->id, fn() => 'https://app.test/cb', 'user:1');
    } catch (InvalidArgumentException $e) {
        $thrown = true;
    }
    expect($thrown)->toBeTrue();
});

test('e complete sets row connected and creates binding', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $row = new SocialAccount;
    $row->business_id = $this->biz->id;
    $row->platform = 'facebook';
    $row->status = 'pending';
    $row->provider_profile_ref = 'profile_6001';
    $row->save();

    $connections = app(SocialConnections::class);
    $result = $connections->complete('facebook', 'acct_fb_6003', 'profile_6001', 'profile_6001', 'Joes Diner', 'user:1');

    expect($result->id)->toBe($row->id);

    $row->refresh();
    expect($row->status)->toBe('connected');
    expect($row->account_ref)->toBe('acct_fb_6003');
    expect($row->account_handle)->toBe('Joes Diner');

    $binding = ZernioAccountBinding::where('account_ref', 'acct_fb_6003')->first();
    expect($binding)->not->toBeNull();
    expect($binding->platform)->toBe('facebook');
});

test('f complete with null accountRef finds single account', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    Http::fake([
        'zernio.com/api/v1/accounts*instagram*' => Http::response(['accounts' => [['_id' => 'acct_ig_6004', 'profileId' => 'profile_6001', 'platform' => 'instagram']]]),
    ]);

    $row = new SocialAccount;
    $row->business_id = $this->biz->id;
    $row->platform = 'instagram';
    $row->status = 'pending';
    $row->provider_profile_ref = 'profile_6001';
    $row->save();

    $connections = app(SocialConnections::class);
    $connections->complete('instagram', null, 'profile_6001', null, 'Insta', 'user:1');

    $row->refresh();
    expect($row->status)->toBe('connected');
    expect($row->account_ref)->toBe('acct_ig_6004');

});

test('f2 complete throws if multiple accounts found', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    Http::fake([
        'zernio.com/api/v1/accounts*instagram*' => Http::response(['accounts' => [['_id' => 'acct_ig_6004'], ['_id' => 'acct_ig_6005']]]),
    ]);

    $row = new SocialAccount();
    $row->business_id = $this->biz->id;
    $row->platform = 'instagram';
    $row->status = 'pending';
    $row->provider_profile_ref = 'profile_6001';
    $row->save();
    
    $connections = app(SocialConnections::class);

    $thrown = false;
    try {
        $connections->complete('instagram', null, 'profile_6001', null, 'Insta', 'user:1');
    } catch (ZernioConnectionRefused $e) {
        $thrown = true;
    }
    expect($thrown)->toBeTrue();
    
    $row->refresh();
    expect($row->status)->toBe('pending');
});
