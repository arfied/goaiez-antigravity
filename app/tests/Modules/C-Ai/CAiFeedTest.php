<?php

declare(strict_types=1);

namespace Tests\Modules\CAi;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Actions\AgentAnswerAction;
use App\Modules\CAgent\Events\AgentTurnStarted;
use App\Modules\CAi\Models\AiCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CAiFeedTest extends TestCase
{
    public function test_an_agent_turn_records_an_ai_call(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = {$biz->id}");

        $countBefore = AiCall::where('business_id', $biz->id)->count();

        Event::dispatch(new AgentTurnStarted(
            businessId: $biz->id,
            conversationId: null,
            turnNumber: 1,
            userMessage: 'distinctive message for testing'
        ));

        $countAfter = AiCall::where('business_id', $biz->id)->count();
        $this->assertEquals($countBefore + 1, $countAfter);

        $call = AiCall::where('business_id', $biz->id)->orderBy('id', 'desc')->first();
        $this->assertEquals('agent.turn', $call->task);
        $this->assertEquals('simulated', $call->provider);
        $this->assertEquals('default_primary', $call->model_served);
    }

    public function test_the_agent_announces_every_turn_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Event::fake([AgentTurnStarted::class]);

        app(AgentAnswerAction::class)->handle(
            businessId: $biz->id,
            userMessage: 'hello',
            conversationId: null,
            turnNumber: 1
        );

        Event::assertDispatchedTimes(AgentTurnStarted::class, 1);
    }
}
