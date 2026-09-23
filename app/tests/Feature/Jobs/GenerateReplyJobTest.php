<?php

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\ReviewSource;
use App\Enums\UserRole;
use App\Jobs\Reviews\GenerateReplyJob;
use App\Models\AutopilotSettings;
use App\Models\Location;
use App\Models\Reply;
use App\Models\Review;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Gbp\GbpReview;
use App\Services\Gbp\GoogleReviewIngest;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

it('tests GenerateReplyJob draft written', function () {
    Mail::fake();
    Notification::fake();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id' => 'msg_eval',
            'type' => 'message',
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Faked reply draft']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ]),
        'api.openai.com/*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo-0613',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Faked reply draft',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 9, 'completion_tokens' => 12, 'total_tokens' => 21],
        ]),
    ]);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 1000, 'test');

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $review = Review::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'source' => ReviewSource::Google,
        'rating' => 1,
    ]);

    $job = new GenerateReplyJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();

    // Check a draft is generated. It writes a ReviewReply.
    $replies = Reply::where('review_id', $review->id)->count();
    expect($replies)->toBe(1);
});

it('tests GenerateReplyJob left alone if replied', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $review = Review::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'source' => ReviewSource::Google,
        'rating' => 1,
        'raw_payload' => ['has_owner_reply' => true],
    ]);

    $job = new GenerateReplyJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();

    $replies = Reply::where('review_id', $review->id)->count();
    expect($replies)->toBe(0);
});

it('tests GenerateReplyJob is dispatched by ingest', function () {
    Queue::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    $location = Location::factory()->create(['business_id' => $biz->id]);

    AutopilotSettings::factory()->create(['location_id' => $location->id, 'auto_reply' => true]);

    $ingest = app(GoogleReviewIngest::class);
    $gbpReview = new GbpReview(
        externalId: 'google-123',
        rating: 1,
        comment: 'test',
        authorName: 'test',
        createdAt: Carbon::now(),
        updatedAt: Carbon::now(),
        hasOwnerReply: false,
        ownerReplyReported: false,
    );

    $ingest->upsertOne($location, $gbpReview);

    Queue::assertPushed(GenerateReplyJob::class);
});
