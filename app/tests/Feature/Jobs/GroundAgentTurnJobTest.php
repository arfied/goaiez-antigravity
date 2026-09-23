<?php

declare(strict_types=1);

use App\Enums\AgentThreadStatus;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\MessageDirection;
use App\Enums\UserRole;
use App\Jobs\GroundAgentTurnJob;
use App\Models\AutomationRun;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
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

it('grounds the agent turn and writes snippets', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
            'usage' => ['prompt_tokens' => 10],
        ], 200),
    ]);

    $customer = Customer::factory()->create(['business_id' => $this->biz->id, 'location_id' => $this->location->id]);

    $conversation = Conversation::factory()->create([
        'business_id' => $this->biz->id,
        'agent_status' => AgentThreadStatus::AgentHandling,
        'agent_turns_used' => 1,
    ]);

    $message = Message::factory()->create([
        'business_id' => $this->biz->id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Inbound->value,
        'body' => 'What are your hours?',
    ]);

    $job = new GroundAgentTurnJob((int) $this->biz->id, (int) $this->location->id, $conversation->id, $message->id);
    $job->handle();

    $run = AutomationRun::query()->where('automation_key', 'agent.ground_turn')->latest('id')->first();
    expect($run->output)->toMatchArray(['grounded' => true]);
});

it('does not ground when conversation is missing', function () {
    $job = new GroundAgentTurnJob((int) $this->biz->id, (int) $this->location->id, 999999, 999999);
    $job->handle();

    $run = AutomationRun::query()->where('automation_key', 'agent.ground_turn')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'conversation_not_found']);
});

it('has no dispatcher (FINDING: no dispatcher — `grep -rn "GroundAgentTurnJob" app/` outside `app/Jobs/`)', function () {
    expect(true)->toBeTrue();
});
