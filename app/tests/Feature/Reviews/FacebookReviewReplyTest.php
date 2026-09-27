<?php

declare(strict_types=1);

use App\Enums\ReviewSource;
use App\Enums\UserRole;
use App\Livewire\Account\FacebookReviews;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Modules\X182\Models\SocialAccount;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant();
    $this->owner = User::find($this->biz->owner_user_id);
    Tenancy::set((int) $this->biz->id);

    $this->location = Location::factory()->create(['business_id' => $this->biz->id]);

    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');

    $this->account = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'location_id' => $this->location->id,
        'provider_profile_ref' => 'profile_7701',
    ]);
    $this->account->account_ref = 'acct_fb_7701';
    $this->account->save();

    $this->review = Review::factory()->create([
        'location_id' => $this->location->id,
        'business_id' => $this->biz->id,
        'source' => ReviewSource::Facebook,
        'provider_review_id' => 'fbr_7702',
        'rating' => 5,
    ]);
});

it('replies to facebook review and updates review record', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/reviews/fbr_7702/reply' => Http::response(['success' => true, 'reply' => ['id' => 'rep_7703']], 200),
    ]);

    $this->actingAs($this->owner);

    Livewire::test(FacebookReviews::class)
        ->set('replyText.'.$this->review->id, 'Thank you 7704')
        ->call('reply', $this->review->id);

    $this->review->refresh();
    expect($this->review->owner_reply_text)->toBe('Thank you 7704')
        ->and($this->review->owner_reply_ref)->toBe('rep_7703')
        ->and($this->review->owner_replied_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request['accountId'] === 'acct_fb_7701'
            && $request['message'] === 'Thank you 7704'
            && $request->hasHeader('Idempotency-Key', 'fb-review-reply-'.$this->review->id);
    });
});

it('does nothing when text is empty', function () {
    Http::fake();

    $this->actingAs($this->owner);

    Livewire::test(FacebookReviews::class)
        ->set('replyText.'.$this->review->id, '')
        ->call('reply', $this->review->id);

    Http::assertNothingSent();
    $this->review->refresh();
    expect($this->review->owner_replied_at)->toBeNull();
});

it('does nothing when meta platform error 400 occurs', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/reviews/fbr_7702/reply' => Http::response(['error' => 'Invalid parameter', 'code' => 'platform_api_error'], 400),
    ]);

    $this->actingAs($this->owner);

    Livewire::test(FacebookReviews::class)
        ->set('replyText.'.$this->review->id, 'Thank you 7704')
        ->call('reply', $this->review->id);

    $this->review->refresh();
    expect($this->review->owner_replied_at)->toBeNull();
});

it('does nothing when no connected facebook account for the location', function () {
    $this->account->location_id = null;
    $this->account->save();

    Http::fake();

    $this->actingAs($this->owner);

    Livewire::test(FacebookReviews::class)
        ->set('replyText.'.$this->review->id, 'Thank you 7704')
        ->call('reply', $this->review->id);

    Http::assertNothingSent();
});

it('does nothing when already replied', function () {
    $this->review->owner_replied_at = now();
    $this->review->save();

    Http::fake();

    $this->actingAs($this->owner);

    Livewire::test(FacebookReviews::class)
        ->set('replyText.'.$this->review->id, 'Thank you 7704')
        ->call('reply', $this->review->id);

    Http::assertNothingSent();
});

it('forbids staff user', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Http::fake();

    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set((int) $this->biz->id);

    Livewire::test(FacebookReviews::class)
        ->set('replyText.'.$this->review->id, 'Thank you 7704')
        ->call('reply', $this->review->id)
        ->assertForbidden();

    Http::assertNothingSent();
});

it('displays textarea and reply button then the replied text on the screen', function () {
    $this->actingAs($this->owner);

    $this->get(route('account.facebook-reviews'))
        ->assertOk()
        ->assertSee('Reply on Facebook');

    $this->review->owner_replied_at = now();
    $this->review->owner_reply_text = 'Thank you 7704';
    $this->review->save();

    $this->get(route('account.facebook-reviews'))
        ->assertOk()
        ->assertSee('You replied: Thank you 7704')
        ->assertDontSee('Reply on Facebook');
});
