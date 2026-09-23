<?php

declare(strict_types=1);

namespace Tests\Modules\X220;

use App\Enums\AiTask;
use App\Models\AiCall;
use App\Modules\X220\Actions\EvalCompareAction;
use App\Modules\X220\Actions\EvalRunAction;
use App\Modules\X220\Actions\PromptFreezeAction;
use App\Modules\X220\Actions\PromptRegisterAction;
use App\Modules\X220\Actions\PromptResolveAction;
use App\Modules\X220\Events\EvalCompleted;
use App\Modules\X220\Events\PromptFrozen;
use App\Modules\X220\Models\AiPrompt;
use App\Modules\X220\Models\GoldenSet;
use App\Modules\X220\Ui\PromptHistory;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class X220Test extends TestCase
{
    private PromptResolveAction $resolver;

    private PromptFreezeAction $freezer;

    private EvalRunAction $evaluator;

    private EvalCompareAction $comparator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PromptResolveAction;
        $this->freezer = new PromptFreezeAction;
        $this->evaluator = new EvalRunAction;
        $this->comparator = new EvalCompareAction($this->evaluator);
    }

    /**
     * [N-220-01] prompt resolution by key and version
     */
    public function test_n_220_01_prompt_resolution(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Prompt Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p1 = AiPrompt::create([
            'business_id' => $biz->id,
            'prompt_key' => 'lead.qualify',
            'version' => 1,
            'body' => 'You are a qualification assistant v1',
        ]);

        $resolved = $this->resolver->handle($biz->id, 'lead.qualify', 1);
        $this->assertNotNull($resolved);
        $this->assertEquals($p1->id, $resolved->id);
    }

    /**
     * [N-220-02] prompt freeze immutability
     */
    public function test_n_220_02_prompt_freeze(): void
    {
        Event::fake([PromptFrozen::class]);

        $biz = TestCase::provisionTenant(['name' => 'Freeze Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = AiPrompt::create([
            'business_id' => $biz->id,
            'prompt_key' => 'review.reply',
            'version' => 1,
            'body' => 'You write review replies',
        ]);

        $frozen = $this->freezer->handle($biz->id, $p->id);
        $this->assertNotNull($frozen->frozen_at);

        Event::assertDispatched(PromptFrozen::class);
    }

    /**
     * [N-220-03] golden set evaluation run
     */
    public function test_n_220_03_golden_set_eval_run(): void
    {
        Event::fake([EvalCompleted::class]);

        $biz = TestCase::provisionTenant(['name' => 'Eval Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = AiPrompt::create([
            'business_id' => $biz->id,
            'prompt_key' => 'booking.agent',
            'version' => 1,
            'body' => 'Book the appointment',
        ]);

        GoldenSet::create([
            'business_id' => $biz->id,
            'prompt_id' => $p->id,
            'prompt_version' => 1,
            'test_cases' => [['input' => 'book oil change', 'expected' => 'booked']],
            'score_threshold' => 80,
        ]);

        $res = $this->evaluator->handle($biz->id, $p->id);
        $this->assertTrue($res['passed']);
        $this->assertGreaterThanOrEqual(80, $res['score']);

        Event::assertDispatched(EvalCompleted::class);
    }

    /**
     * [N-220-04] eval comparison between versions
     */
    public function test_n_220_04_eval_comparison(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Compare Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p1 = AiPrompt::create(['business_id' => $biz->id, 'prompt_key' => 'chat.v', 'version' => 1, 'body' => 'v1']);
        $p2 = AiPrompt::create(['business_id' => $biz->id, 'prompt_key' => 'chat.v', 'version' => 2, 'body' => 'v2']);

        $res = $this->comparator->handle($biz->id, $p1->id, $p2->id);
        $this->assertArrayHasKey('delta_score', $res);
        $this->assertArrayHasKey('regression', $res);
    }

    public function test_c2a_prompt_register_creates_v1_and_v2(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Prompt Biz', 'currency' => 'USD']);

        $action = app(PromptRegisterAction::class);

        $prompt1 = $action->handle($biz->id, 'test.key', 'TestClass', 'body 1');
        $this->assertEquals(1, $prompt1->version);
        $this->assertEquals('body 1', $prompt1->body);

        $prompt1Same = $action->handle($biz->id, 'test.key', 'TestClass', 'body 1');
        $this->assertEquals($prompt1->id, $prompt1Same->id);

        $prompt2 = $action->handle($biz->id, 'test.key', 'TestClass', 'body 2');
        $this->assertEquals(2, $prompt2->version);
        $this->assertEquals('body 2', $prompt2->body);

        $prompt1Fresh = AiPrompt::find($prompt1->id);
        $this->assertNull($prompt1Fresh->frozen_at); // leaves v1 intact (frozen or not)

        Http::assertNothingSent();
    }

    public function test_c2a_airouter_dispatch_writes_prompt_id(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Router Biz', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        \App\Modules\CAi\Models\AiTask::create([
            'business_id' => $biz->id,
            'task_name' => 'conversation',
            'max_ttft_ms' => 1000,
            'cost_limit_cents' => 5000,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_eval',
                'choices' => [
                    ['message' => ['content' => 'fake answer']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_eval',
                'type' => 'message',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'fake answer']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
        ]);

        $router = app(AiRouter::class);
        $router->dispatch(new AiRequest(
            task: AiTask::Conversation,
            prompt: 'Hello',
            promptKey: 'test.router.key'
        ));

        $call = AiCall::where('business_id', $biz->id)->latest('id')->first();
        $this->assertNotNull($call);
        $this->assertNotNull($call->prompt_id);
        $this->assertEquals(1, $call->prompt_version);

        $router->dispatch(new AiRequest(
            task: AiTask::Conversation,
            prompt: 'Hello 2',
        ));
        $callNoKey = AiCall::where('business_id', $biz->id)->latest('id')->first();
        $this->assertNotNull($callNoKey->prompt_id);
        $this->assertEquals(1, $callNoKey->prompt_version);
        $this->assertEquals(AiTask::Conversation->value, AiPrompt::find($callNoKey->prompt_id)->prompt_key);
    }

    public function test_c2a_prompt_history_screen_and_freeze(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'History Biz', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $action = app(PromptRegisterAction::class);
        $prompt = $action->handle($biz->id, 'test.screen.key', 'TestClass', 'body 1');

        Livewire::test(PromptHistory::class)
            ->assertSee('test.screen.key')
            ->assertSee('v1')
            ->call('freeze', $prompt->id);

        $this->assertNotNull($prompt->fresh()->frozen_at);
        Http::assertNothingSent();
    }
}
