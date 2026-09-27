<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Modules\X182\Ui\SocialQueue;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant(['name' => 'Social Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');
    Config::set('credentials.zernio_webhook_secret', 'test_secret');
});

test('a facebook text post', function () {
    Http::fake([
        'zernio.com/api/v1/posts*' => Http::response([
            'post' => [
                '_id' => 'post_7001',
                'status' => 'published',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'published',
                        'platformPostId' => 'fb_7001',
                        'platformPostUrl' => 'https://facebook.com/7001',
                    ],
                ],
            ],
        ], 201),
    ]);

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello World')
        ->set('imageUrl', '')
        ->call('publish');

    $post = SocialPost::first();
    expect($post->publish_status)->toBe('published')
        ->and($post->is_published)->toBeTrue()
        ->and($post->provider_post_id)->toBe('post_7001')
        ->and($post->platform_post_url)->toBe('https://facebook.com/7001');

    Http::assertSent(function ($request) use ($post) {
        return $request->header('Idempotency-Key')[0] === 'social-post-'.$post->id
            && $request['platforms'] === [['platform' => 'facebook', 'accountId' => 'acct_fb_7001']]
            && $request['publishNow'] === true;
    });
});

test('b instagram with NO image', function () {
    Http::fake();

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'instagram',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7002',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_ig_7002';
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello Insta')
        ->set('imageUrl', '')
        ->call('publish');

    Http::assertNothingSent();
    expect(SocialPost::count())->toBe(0);
});

test('c facebook with non-https image url', function () {
    Http::fake();

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello')
        ->set('imageUrl', 'http://example.test/a.jpg')
        ->call('publish');

    Http::assertNothingSent();
    expect(SocialPost::count())->toBe(0);
});

test('d 207 response', function () {
    Http::fake([
        'zernio.com/api/v1/posts*' => Http::response([
            'post' => [
                '_id' => 'post_7003',
                'status' => 'failed',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'failed',
                        'errorMessage' => 'Page not eligible 7003',
                    ],
                ],
            ],
        ], 207),
    ]);

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello')
        ->set('imageUrl', '')
        ->call('publish');

    $post = SocialPost::first();
    expect($post->publish_status)->toBe('failed')
        ->and($post->last_error)->toBe('Page not eligible 7003');

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('not published: Page not eligible 7003');
});

test('e 409 duplicate', function () {
    Http::fake([
        'zernio.com/api/v1/posts*' => Http::response([
            'details' => ['existingPostId' => 'post_7004'],
        ], 409),
    ]);

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello')
        ->set('imageUrl', '')
        ->call('publish');

    $post = SocialPost::first();
    expect($post->publish_status)->toBe('duplicate')
        ->and($post->provider_post_id)->toBe('post_7004');
});

test('f 403 response', function () {
    Http::fake([
        'zernio.com/api/v1/posts*' => Http::response([
            'error' => 'ACCOUNT_DISCONNECTED',
        ], 403),
    ]);

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello')
        ->set('imageUrl', '')
        ->call('publish');

    $post = SocialPost::first();
    expect($post->publish_status)->toBe('failed');
    expect(str_starts_with($post->last_error, 'Zernio refused it:'))->toBeTrue();
});

test('g staff user', function () {
    Http::fake();

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello')
        ->set('imageUrl', '')
        ->call('publish')
        ->assertForbidden();

    Http::assertNothingSent();
    expect(SocialPost::count())->toBe(0);
});

test('h account with NO account_ref', function () {
    Http::fake();

    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    // no account_ref
    $acct->save();

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->set('accountId', $acct->id)
        ->set('content', 'Hello')
        ->set('imageUrl', '')
        ->call('publish');

    Http::assertNothingSent();
    expect(SocialPost::count())->toBe(0);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('Connect a Facebook Page or an Instagram account first');
});

test('i real GET as the owner with connected account', function () {
    Tenancy::set($this->biz->id);
    $acct = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'account_handle' => 'joesdiner-7001',
        'provider_profile_ref' => 'profile_7001',
    ]);
    $acct->account_ref = 'acct_fb_7001';
    $acct->save();

    $this->actingAs($this->owner);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('Publish now')
        ->assertSee('Facebook · joesdiner-7001');
});
