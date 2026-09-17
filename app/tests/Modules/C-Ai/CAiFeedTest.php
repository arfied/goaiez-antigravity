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
    public function test_an_agent_turn_records_no_ai_call_until_a_model_is_used(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = {$biz->id}");

        $countBefore = AiCall::where('business_id', $biz->id)->count();
        $this->assertEquals(0, $countBefore);

        Event::dispatch(new AgentTurnStarted(
            businessId: $biz->id,
            conversationId: null,
            turnNumber: 1,
            userMessage: 'distinctive message for testing'
        ));

        $countAfter = AiCall::where('business_id', $biz->id)->count();
        $this->assertEquals(0, $countAfter);
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
