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
        'provider_post_id' => 'post_7901',
    ]);

    $this->c = Comment::create([
        'business_id' => $this->biz->id,
        'post_id' => $this->postRow->id,
        'platform_comment_id' => 'fbc_8001',
        'author_name' => 'Pat Reader',
        'comment_text' => 'Love this 8002',
        'sentiment' => 'unclassified',
        'is_publicly_replied' => false,
    ]);

    $this->actingAs($this->owner);
});

test('a reply comment success', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/comments/post_7901' => Http::response(['success' => true, 'data' => ['commentId' => 'fbc_8003', 'isReply' => true]], 200),
    ]);

    Livewire::test(SocialQueue::class)
        ->set('commentReply.'.$this->c->id, 'Thanks Pat 8004')
        ->call('replyToComment', $this->c->id);

    $this->c->refresh();
    expect($this->c->reply_text)->toBe('Thanks Pat 8004');
    expect($this->c->reply_ref)->toBe('fbc_8003');
    expect($this->c->replied_at)->not->toBeNull();
    expect($this->c->is_publicly_replied)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://zernio.com/api/v1/inbox/comments/post_7901' &&
               $request->method() === 'POST' &&
               $request['accountId'] === 'acct_fb_7101' &&
               $request['message'] === 'Thanks Pat 8004' &&
               $request['commentId'] === 'fbc_8001' &&
               $request->header('Idempotency-Key')[0] === 'comment-reply-'.$this->c->id;
    });
});

test('b empty text', function () {
    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('commentReply.'.$this->c->id, '   ')
        ->call('replyToComment', $this->c->id);

    Http::assertNothingSent();

    $this->c->refresh();
    expect($this->c->replied_at)->toBeNull();
    expect($this->c->is_publicly_replied)->toBeFalse();
});

test('c 403', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/comments/post_7901' => Http::response(['error' => 'Not permitted', 'code' => 'platform_api_error', 'type' => 'platform_error'], 403),
    ]);

    Livewire::test(SocialQueue::class)
        ->set('commentReply.'.$this->c->id, 'Thanks Pat 8004')
        ->call('replyToComment', $this->c->id);

    $this->c->refresh();
    expect($this->c->replied_at)->toBeNull();
    expect($this->c->is_publicly_replied)->toBeFalse();
});

test('d already replied', function () {
    $this->c->update([
        'replied_at' => now(),
        'reply_text' => 'Already done',
    ]);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('commentReply.'.$this->c->id, 'Thanks Pat 8004')
        ->call('replyToComment', $this->c->id);

    Http::assertNothingSent();
});

test('e staff', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('commentReply.'.$this->c->id, 'Thanks Pat 8004')
        ->call('replyToComment', $this->c->id)
        ->assertForbidden();

    Http::assertNothingSent();
});

test('f real GET route', function () {
    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('Pat Reader: Love this 8002')
        ->assertSee('Reply');

    $this->c->update([
        'replied_at' => now(),
        'reply_text' => 'Thanks Pat 8004',
    ]);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('You replied: Thanks Pat 8004');
});
