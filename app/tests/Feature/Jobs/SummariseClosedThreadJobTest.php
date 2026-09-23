<?php

declare(strict_types=1);

use App\Enums\AgentThreadStatus;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\MessageDirection;
use App\Enums\UserRole;
use App\Jobs\SummariseClosedThreadJob;
use App\Models\AuditLogEntry;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use App\Services\Agent\AgentThreadStates;
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

    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 1000000, 'test');
});

afterEach(function () {
    Tenancy::forget();
});

it('gets its summary written (AI faked) for a closed thread', function () {
    Http::fake([
        '*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'This is a summary.']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10, 'prompt_tokens' => 10, 'completion_tokens' => 10],
            'choices' => [['message' => ['content' => 'This is a summary.']]],
        ], 200),
    ]);

    $customer = Customer::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id]);

    $conversation = Conversation::factory()->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'customer_id' => $customer->id,
        'agent_status' => AgentThreadStatus::Closed,
        'consent_logged_at' => now(),
    ]);

    Message::factory()->create([
        'business_id' => $this->biz->id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Inbound->value,
        'body' => 'I have a problem.',
    ]);

    $job = new SummariseClosedThreadJob((int) $this->biz->id, (int) $this->location->id, $conversation->id, AgentThreadStatus::Closed);
    $job->handle();

    $log = AuditLogEntry::query()->where('action', 'agent.thread.closed')->latest()->first();

    expect($log)->not->toBeNull();
    expect(((array) $log->metadata)['summary_from_model'] ?? false)->toBeTrue();
});

it('leaves an open thread alone', function () {
    Queue::fake();

    $customer = Customer::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id]);

    $conversation = Conversation::factory()->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'customer_id' => $customer->id,
        'agent_status' => AgentThreadStatus::AgentHandling,
        'consent_logged_at' => now(),
    ]);

    app(AgentThreadStates::class)->recordTurn($conversation);
    Queue::assertNotPushed(SummariseClosedThreadJob::class);
});

it('is dispatched by AgentThreadStates on close', function () {
    Queue::fake();

    $customer = Customer::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id]);

    $conversation = Conversation::factory()->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'customer_id' => $customer->id,
        'agent_status' => AgentThreadStatus::AgentHandling,
        'consent_logged_at' => now(),
    ]);

    app(AgentThreadStates::class)->close($conversation);

    Queue::assertPushed(SummariseClosedThreadJob::class, fn ($j) => $j->conversationId === $conversation->id);
});
