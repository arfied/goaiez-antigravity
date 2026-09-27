<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Modules\X182\Ui\SocialQueue;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');

    $this->biz = TestCase::provisionTenant(['name' => 'Social Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    Tenancy::set($this->biz->id);

    GbpProfileBinding::create([
        'business_id' => $this->biz->id,
        'profile_ref' => 'profile_7101',
    ]);

    $this->account = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_7101',
    ]);
    $this->account->account_ref = 'acct_fb_7101';
    $this->account->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_7101',
        'profile_ref' => 'profile_7101',
        'platform' => 'facebook',
    ]);

    $this->postRow = SocialPost::create([
        'business_id' => $this->biz->id,
        'account_id' => $this->account->id,
        'content_text' => 'test post',
        'publish_status' => 'published',
        'provider_post_id' => 'post_7911',
    ]);

    $this->c = Comment::create([
        'business_id' => $this->biz->id,
        'post_id' => $this->postRow->id,
        'platform_comment_id' => 'fbc_8011',
        'author_name' => 'Pat Reader',
        'comment_text' => 'Hide me 8012',
        'sentiment' => 'unclassified',
        'is_publicly_replied' => false,
    ]);

    $this->actingAs($this->owner);
});

test('a hide succeeds', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/comments/post_7911/fbc_8011/hide' => Http::response(['status' => 'success', 'commentId' => 'fbc_8011', 'hidden' => true, 'platform' => 'facebook'], 200),
    ]);

    Livewire::test(SocialQueue::class)
        ->call('hideComment', $this->c->id);

    $this->c->refresh();
    expect($this->c->hidden_at)->not->toBeNull();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://zernio.com/api/v1/inbox/comments/post_7911/fbc_8011/hide' &&
               $request->method() === 'POST' &&
               $request['accountId'] === 'acct_fb_7101';
    });
});

test('b unhide succeeds', function () {
    $this->c->update(['hidden_at' => now()]);

    Http::fake([
        'zernio.com/api/v1/inbox/comments/post_7911/fbc_8011/hide*' => Http::response(['status' => 'success', 'commentId' => 'fbc_8011', 'hidden' => false, 'platform' => 'facebook'], 200),
    ]);

    Livewire::test(SocialQueue::class)
        ->call('unhideComment', $this->c->id);

    $this->c->refresh();
    expect($this->c->hidden_at)->toBeNull();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://zernio.com/api/v1/inbox/comments/post_7911/fbc_8011/hide?accountId=acct_fb_7101' &&
               $request->method() === 'DELETE';
    });
});

test('c 400 limitation', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/comments/post_7911/fbc_8011/hide' => Http::response(['error' => 'Cannot hide', 'code' => 'PLATFORM_LIMITATION', 'type' => 'platform_error'], 400),
    ]);

    Livewire::test(SocialQueue::class)
        ->call('hideComment', $this->c->id);

    $this->c->refresh();
    expect($this->c->hidden_at)->toBeNull();
});

test('d already hidden', function () {
    $this->c->update(['hidden_at' => now()]);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->call('hideComment', $this->c->id);

    Http::assertNothingSent();
});

test('e staff', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->call('hideComment', $this->c->id)
        ->assertForbidden();

    Http::assertNothingSent();
});

test('f platform_comment_id null', function () {
    $this->c->update(['platform_comment_id' => null]);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->call('hideComment', $this->c->id);

    Http::assertNothingSent();
    $this->c->refresh();
    expect($this->c->hidden_at)->toBeNull();
});

test('g another tenant', function () {
    $other = TestCase::provisionTenant(['name' => 'Other Biz']);
    Tenancy::set((int) $other->id);

    $otherAccount = SocialAccount::create([
        'business_id' => $other->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_7201',
    ]);
    $otherAccount->account_ref = 'acct_fb_7201';
    $otherAccount->save();

    $otherPost = SocialPost::create([
        'business_id' => $other->id,
        'account_id' => $otherAccount->id,
        'content_text' => 'other post',
        'publish_status' => 'published',
        'provider_post_id' => 'post_7211',
    ]);

    $otherComment = Comment::create([
        'business_id' => $other->id,
        'post_id' => $otherPost->id,
        'platform_comment_id' => 'fbc_7211',
        'author_name' => 'Pat Reader',
        'comment_text' => 'Other comment',
        'sentiment' => 'unclassified',
        'is_publicly_replied' => false,
    ]);

    Tenancy::set((int) $this->biz->id);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->call('hideComment', $otherComment->id);

    Http::assertNothingSent();

    Tenancy::set((int) $other->id);
    $otherComment->refresh();
    expect($otherComment->hidden_at)->toBeNull();
});

test('h real GET route', function () {
    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('wire:click="hideComment('.$this->c->id.')"', false);

    $this->c->update(['hidden_at' => now()]);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('(hidden — only the commenter and your page see it)')
        ->assertSee('wire:click="unhideComment('.$this->c->id.')"', false);
});
