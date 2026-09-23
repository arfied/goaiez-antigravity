<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Exceptions\ResponseTemplateRefused;
use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReplyGenerator;
use App\Services\Reviews\ResponseTemplates;
use App\Services\Reviews\ReviewHubPages;
use App\Support\Tenancy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->location = $this->biz->locations()->first();
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('a written reviews.templates.max_body_length of 5 makes a 6-char body refuse through ResponseTemplates', function () {
    PlatformSetting::write('reviews.templates.max_body_length', 5, 'test');
    app(ResponseTemplates::class)->add('Test', '123456', 'actor');
})->throws(ResponseTemplateRefused::class);

it('a written hub max_reviews of 1 renders one', function () {
    PlatformSetting::write('reviews.hub.max_reviews', 1, 'test');

    Review::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id, 'source' => 'first_party', 'display_on_website' => true, 'rating' => 5, 'status' => ReviewStatus::Approved, 'moderation_flags' => '[]']);
    Review::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id, 'source' => 'first_party', 'display_on_website' => true, 'rating' => 5, 'status' => ReviewStatus::Approved, 'moderation_flags' => '[]']);

    $collection = app(ReviewHubPages::class)->displayedFor($this->location);
    expect($collection)->toHaveCount(1);
});

it('a written recovery length caps the reply', function () {
    PlatformSetting::write('reviews.reply.max_recovery_length', 33, 'test');

    $review = Review::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id]);

    Http::fake(['*' => Http::response(['content' => [['text' => 'ok']]], 200)]);

    $draft = app(ReplyGenerator::class)->draftRecovery($review, $this->location, $this->biz);
    Http::assertSent(function (Request $request) {
        return str_contains(json_encode($request->data()), 'At most 33 characters');
    });
});
