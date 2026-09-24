<?php

namespace Tests\Modules\X102;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Actions\ChatQuestionReadAction;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Models\ChatTurn;
use App\Support\Tenancy;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatQuestionReadActionTest extends TestCase
{
    public function test_it_returns_questions_and_ignores_greetings_and_old_turns(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        Tenancy::set($biz->id);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => Str::random(10),
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);

        $t1 = ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => 'Distinctive question 4501?',
        ]);

        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => 'thanks a lot 4502',
        ]);

        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'agent',
            'message' => 'you are welcome',
            'refusal_code' => null,
        ]);

        $t3 = ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => 'do you work weekends 4503',
            'created_at' => now()->addMinutes(1),
        ]);

        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'agent',
            'message' => 'I cannot answer that.',
            'refusal_code' => 'NO_FACT',
        ]);

        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => 'Old question 4504?',
            'created_at' => now()->subDays(120),
        ]);

        $action = new ChatQuestionReadAction;
        $results = $action->recent($biz->id, 90, 10);

        $this->assertCount(2, $results);
        $this->assertEquals($t3->id, $results[0]['id']);
        $this->assertEquals('do you work weekends 4503', $results[0]['question']);
        $this->assertEquals($t1->id, $results[1]['id']);
        $this->assertEquals('Distinctive question 4501?', $results[1]['question']);

        $this->assertCount(1, $action->recent($biz->id, 90, 1));
    }
}
