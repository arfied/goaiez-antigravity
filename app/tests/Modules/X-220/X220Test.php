<?php

declare(strict_types=1);

namespace Tests\Modules\X220;

use App\Modules\X121\Models\Business;
use App\Modules\X220\Actions\EvalCompareAction;
use App\Modules\X220\Actions\EvalRunAction;
use App\Modules\X220\Actions\PromptFreezeAction;
use App\Modules\X220\Actions\PromptResolveAction;
use App\Modules\X220\Events\EvalCompleted;
use App\Modules\X220\Events\PromptFrozen;
use App\Modules\X220\Models\AiPrompt;
use App\Modules\X220\Models\GoldenSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $biz = Business::provision(['name' => 'Prompt Tenant', 'currency' => 'USD']);
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

        $biz = Business::provision(['name' => 'Freeze Tenant', 'currency' => 'USD']);
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

        $biz = Business::provision(['name' => 'Eval Tenant', 'currency' => 'USD']);
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
        $biz = Business::provision(['name' => 'Compare Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p1 = AiPrompt::create(['business_id' => $biz->id, 'prompt_key' => 'chat.v', 'version' => 1, 'body' => 'v1']);
        $p2 = AiPrompt::create(['business_id' => $biz->id, 'prompt_key' => 'chat.v', 'version' => 2, 'body' => 'v2']);

        $res = $this->comparator->handle($biz->id, $p1->id, $p2->id);
        $this->assertArrayHasKey('delta_score', $res);
        $this->assertArrayHasKey('regression', $res);
    }
}
