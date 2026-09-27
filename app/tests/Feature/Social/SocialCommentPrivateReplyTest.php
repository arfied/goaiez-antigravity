<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Actions\CommentIngestAction;
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
        'provider_post_id' => 'post_7921',
    ]);

    $this->c = Comment::create([
        'business_id' => $this->biz->id,
        'post_id' => $this->postRow->id,
        'platform_comment_id' => 'fbc_8021',
        'platform_post_id' => 'fbpost_8023',
        'author_name' => 'Pat Reader',
        'comment_text' => 'Ask privately 8022',
        'sentiment' => 'unclassified',
        'is_publicly_replied' => false,
        'commented_at' => now()->subDay(),
    ]);

    $this->actingAs($this->owner);
});

test('a success', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/comments/fbpost_8023/fbc_8021/private-reply' => Http::response(['status' => 'success', 'messageId' => 'm_8024', 'commentId' => 'fbc_8021', 'platform' => 'facebook'], 200),
    ]);

    $id = $this->c->id;

    Livewire::test(SocialQueue::class)
        ->set('privateReply.'.$id, 'Hi Pat 8025')
        ->call('privateReplyToComment', $id);

    $c = $this->c->fresh();
    expect($c->private_reply_text)->toBe('Hi Pat 8025')
        ->and($c->private_replied_at)->not->toBeNull();

    Http::assertSent(function (Request $request) use ($id) {
        $keys = array_keys($request->data());
        sort($keys);

        return $request->url() === 'https://zernio.com/api/v1/inbox/comments/fbpost_8023/fbc_8021/private-reply'
            && $request->method() === 'POST'
            && $request->data()['accountId'] === 'acct_fb_7101'
            && $request->data()['message'] === 'Hi Pat 8025'
            && $request->header('Idempotency-Key')[0] === 'comment-private-reply-'.$id
            && $keys === ['accountId', 'message'];
    });
});

test('b already sent', function () {
    $this->c->update([
        'private_reply_text' => 'Already',
        'private_replied_at' => now(),
    ]);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('privateReply.'.$this->c->id, 'Hi Pat 8025')
        ->call('privateReplyToComment', $this->c->id);

    Http::assertNothingSent();
});

test('c eight days ago', function () {
    $this->c->update([
        'commented_at' => now()->subDays(8),
    ]);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('privateReply.'.$this->c->id, 'Hi Pat 8025')
        ->call('privateReplyToComment', $this->c->id);

    Http::assertNothingSent();

    $c = $this->c->fresh();
    expect($c->private_reply_text)->toBeNull();
});

test('d platform_post_id null', function () {
    $this->c->update([
        'platform_post_id' => null,
    ]);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('privateReply.'.$this->c->id, 'Hi Pat 8025')
        ->call('privateReplyToComment', $this->c->id);

    Http::assertNothingSent();
});

test('e Zernio 400', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Private reply already sent', 'type' => 'platform_error', 'code' => 'platform_api_error', 'details' => ['privateReplyConsumed' => true]], 400),
    ]);

    Livewire::test(SocialQueue::class)
        ->set('privateReply.'.$this->c->id, 'Hi Pat 8025')
        ->call('privateReplyToComment', $this->c->id);

    $c = $this->c->fresh();
    expect($c->private_reply_text)->toBeNull();
});

test('f staff', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Http::fake();

    Livewire::test(SocialQueue::class)
        ->set('privateReply.'.$this->c->id, 'Hi Pat 8025')
        ->call('privateReplyToComment', $this->c->id)
        ->assertForbidden();

    Http::assertNothingSent();
});

test('g ingest', function () {
    $comment = [
        'postId' => 'post_7921',
        'platformPostId' => 'fbpost_8026',
        'id' => 'fbc_8027',
        'author' => ['id' => 'auth_8028', 'name' => 'Name', 'isOwnAccount' => false],
        'text' => 'hello',
        'createdAt' => now()->toIso8601String(),
        'platform' => 'facebook',
    ];

    app(CommentIngestAction::class)->fromZernio($this->biz->id, $comment);

    $row = Comment::where('platform_comment_id', 'fbc_8027')->first();
    expect($row->platform_post_id)->toBe('fbpost_8026');
});

test('h GET', function () {
    $id = $this->c->id;

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('wire:click="privateReplyToComment('.$id.')"', false);

    $this->c->update([
        'private_replied_at' => now(),
        'private_reply_text' => 'Hi Pat 8025',
    ]);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('You messaged them privately: Hi Pat 8025')
        ->assertDontSee('wire:click="privateReplyToComment('.$id.')"', false);
});
