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
    $this->acct = $acct;

    $this->post = SocialPost::create([
        'business_id' => $this->biz->id,
        'account_id' => $this->acct->id,
        'content_text' => 'Testing again',
        'publish_status' => 'unconfirmed',
        'provider_post_id' => 'post_7601',
    ]);
});

test('check again published', function () {
    Http::fake([
        'zernio.com/api/v1/posts/post_7601' => Http::response([
            'post' => [
                '_id' => 'post_7601',
                'status' => 'published',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'published',
                        'platformPostId' => 'fb_7602',
                        'platformPostUrl' => 'https://facebook.com/7602',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->call('checkAgain', $this->post->id);

    $this->post->refresh();
    expect($this->post->publish_status)->toBe('published')
        ->and($this->post->platform_post_url)->toBe('https://facebook.com/7602');

    Http::assertSent(fn ($req) => $req->method() === 'GET' && $req->url() === 'https://zernio.com/api/v1/posts/post_7601');
});

test('check again failed', function () {
    Http::fake([
        'zernio.com/api/v1/posts/post_7601' => Http::response([
            'post' => [
                '_id' => 'post_7601',
                'status' => 'failed',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'failed',
                        'errorMessage' => 'Page restricted 7603',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->call('checkAgain', $this->post->id);

    $this->post->refresh();
    expect($this->post->publish_status)->toBe('failed')
        ->and($this->post->last_error)->toBe('Page restricted 7603');
});

test('check again publishing', function () {
    Http::fake([
        'zernio.com/api/v1/posts/post_7601' => Http::response([
            'post' => [
                '_id' => 'post_7601',
                'status' => 'publishing',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'publishing',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->call('checkAgain', $this->post->id);

    $this->post->refresh();
    expect($this->post->publish_status)->toBe('unconfirmed');
});

test('check again on already published', function () {
    $this->post->update(['publish_status' => 'published']);

    $this->actingAs($this->owner);
    Http::fake();

    Livewire::test(SocialQueue::class)
        ->call('checkAgain', $this->post->id);

    Http::assertNothingSent();
});

test('check again forbidden for staff', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->call('checkAgain', $this->post->id)
        ->assertForbidden();

    Http::assertNothingSent();
});

test('route access', function () {
    $this->actingAs($this->owner);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('Check again');
});
