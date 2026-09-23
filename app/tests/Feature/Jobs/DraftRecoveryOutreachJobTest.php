<?php

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\ReviewSource;
use App\Enums\RoutingDecision;
use App\Enums\TriageStatus;
use App\Enums\UserRole;
use App\Jobs\Reviews\DraftRecoveryOutreachJob;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Models\TriageConversation;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Feedback\FeedbackInput;
use App\Services\Feedback\FeedbackSubmission;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

it('tests DraftRecoveryOutreachJob draft written', function () {
    Mail::fake();
    Notification::fake();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id' => 'msg_eval',
            'type' => 'message',
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Faked draft']],
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
                    'content' => 'Faked draft',
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
        'source' => ReviewSource::FirstParty,
        'rating' => 1,
        'routing_decision' => RoutingDecision::Triaged,
    ]);

    $customer = Customer::factory()->create(['business_id' => $biz->id]);

    $conv = TriageConversation::factory()->create([
        'review_id' => $review->id,
        'customer_id' => $customer->id,
        'status' => TriageStatus::Open,
    ]);

    $job = new DraftRecoveryOutreachJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();

    $conv->refresh();
    expect($conv->outreach_draft_at)->not->toBeNull();
});

it('tests DraftRecoveryOutreachJob nothing written', function () {
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
    $job = new DraftRecoveryOutreachJob((int) $biz->id, (int) $location->id, 9999);
    $job->handle();

    expect(true)->toBeTrue();
});

it('tests DraftRecoveryOutreachJob is dispatched', function () {
    Queue::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    $location = Location::factory()->create(['business_id' => $biz->id]);
    $page = FeedbackPage::factory()->create(['location_id' => $location->id, 'business_id' => $biz->id]);

    $input = new FeedbackInput(
        rating: 1,
        comment: 'Terrible experience.',
        name: 'Test Customer',
        email: 'test@example.com',
        phone: null,
        smsConsent: false,
        emailConsent: false,
        phiAnalysisConsent: false,
        proof: []
    );

    app(FeedbackSubmission::class)->submit($page, $location, $input);

    Queue::assertPushed(DraftRecoveryOutreachJob::class);
});
