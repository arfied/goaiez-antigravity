<?php

declare(strict_types=1);

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\ReviewSource;
use App\Enums\UserRole;
use App\Jobs\AnalyzeReviewJob;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    /** @var TestCase $this */
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    $this->location = Location::factory()->create(['business_id' => $this->biz->id]);
});

afterEach(function () {
    Tenancy::forget();
});

it('analyzes review and writes the analysis', function () {
    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 1000000, 'test');
    $fakeJson = json_encode([
        'flags' => [],
        'sentiment' => 'positive',
        'themes' => ['service'],
    ]);

    Http::fake([
        '*' => Http::response([
            'content' => [['type' => 'text', 'text' => $fakeJson]],
            'choices' => [['message' => ['content' => $fakeJson]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10, 'prompt_tokens' => 10, 'completion_tokens' => 10],
        ], 200),
    ]);

    $review = Review::factory()->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'source' => ReviewSource::FirstParty,
        'rating' => 5,
        'comment' => 'Distinctive great service!',
        'sentiment' => null,
    ]);

    $job = new AnalyzeReviewJob((int) $this->biz->id, (int) $this->location->id, $review->id);
    $job->handle();

    $review->refresh();
    expect($review->sentiment?->value)->toBe('positive');
});

it('does nothing and throws nothing with nothing to analyse', function () {
    $job = new AnalyzeReviewJob((int) $this->biz->id, (int) $this->location->id, 999999);
    $job->handle();
    expect(true)->toBeTrue();
});

it('is dispatched by ReanalyseReviews', function () { /** @var TestCase $this */
    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 1000000, 'test');
    Queue::fake();

    $review = Review::factory()->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'source' => ReviewSource::FirstParty,
        'rating' => 5,
        'comment' => 'Distinctive great service!',
        'moderation_flags' => null,
        'created_at' => now()->subMinutes(20),
    ]);

    $this->artisan('reviews:reanalyse')->assertSuccessful();

    Queue::assertPushed(AnalyzeReviewJob::class, fn ($j) => $j->reviewId === $review->id);
});
