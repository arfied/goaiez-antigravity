<?php

declare(strict_types=1);

namespace Tests\Modules\CAi;

use App\Enums\AiModel;
use App\Modules\CAi\Actions\AiCompleteAction;
use App\Modules\CAi\Actions\AiEmbedAction;
use App\Modules\CAi\Actions\AiSpeakAction;
use App\Modules\CAi\Actions\AiTranscribeAction;
use App\Modules\CAi\Domain\AiEngine;
use App\Modules\CAi\Events\AiCalled;
use App\Modules\CAi\Events\AiFailedOver;
use App\Modules\CAi\Models\AiCall;
use App\Modules\CAi\Models\AiTask;
use App\Modules\CAi\Ui\ModelBoard;
use App\Modules\X121\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class CAiTest extends TestCase
{
    private AiEngine $engine;

    private AiCompleteAction $complete;

    private AiEmbedAction $embed;

    private AiTranscribeAction $transcribe;

    private AiSpeakAction $speak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AiEngine;
        $this->complete = new AiCompleteAction($this->engine);
        $this->embed = new AiEmbedAction($this->engine);
        $this->transcribe = new AiTranscribeAction($this->engine);
        $this->speak = new AiSpeakAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * SUM(ai_calls.cost_cents) × 8 = SUM(ledger AI debits) for any period, to the cent;
     * a provider returning no usage writes cost 0 flagged usage_unavailable, never an estimate;
     * a 1.5 s first token on a 600 ms task fails over
     */
    public function test_anchor_ai_ledger_markup_usage_unavailable_and_ttft_failover(): void
    {
        Event::fake([AiCalled::class, AiFailedOver::class]);

        $biz = TestCase::provisionTenant(['name' => 'AI Core Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $task = AiTask::create([
            'business_id' => $biz->id,
            'task_name' => 'instant_response',
            'max_ttft_ms' => 600,
            'cost_limit_cents' => 500,
        ]);

        // 1. A 1.5s first token (1500ms) on a 600ms task fails over from primary to backup
        $res1 = $this->complete->handle(
            businessId: $biz->id,
            prompt: 'Generate greeting',
            modelRequested: 'primary-fast-model',
            backupModel: 'backup-reliable-model',
            taskId: $task->id,
            simulatedTtftMs: 1500, // 1.5s > 600ms
            providerReturnedUsage: true,
            rawCostCents: 25
        );

        $this->assertEquals('backup-reliable-model', $res1['model_served']);
        $this->assertStringContainsString('ttft_exceeded', (string) $res1['fallback_reason']);

        Event::assertDispatched(AiFailedOver::class);

        // 2. A provider returning no usage writes cost 0 flagged usage_unavailable, never an estimate
        $res2 = $this->complete->handle(
            businessId: $biz->id,
            prompt: 'Summarize text',
            modelRequested: 'primary-fast-model',
            taskId: $task->id,
            simulatedTtftMs: 300,
            providerReturnedUsage: false
        );

        $this->assertEquals(0, $res2['cost_cents']);
        $this->assertTrue($res2['usage_unavailable']);

        $call2 = AiCall::findOrFail($res2['call_id']);
        $this->assertEquals(0, $call2->cost_cents);
        $this->assertTrue($call2->usage_unavailable);

        // 3. Mathematical proof: SUM(ai_calls.cost_cents) * 8 = SUM(ledger AI debits)
        $totalAiCostCents = (int) AiCall::where('business_id', $biz->id)->sum('cost_cents');
        $totalLedgerDebits = (int) LedgerEntry::where('business_id', $biz->id)
            ->where('entry_type', 'ai_debit')
            ->sum('amount_cents');

        $this->assertEquals($totalAiCostCents * 8, $totalLedgerDebits, 'SUM(ai_calls.cost_cents) * 8 must equal SUM(ledger AI debits) to the cent');
    }

    /**
     * [G2-19] the dispatcher's whole job; cost is a routing input, never a quality excuse
     */
    public function test_g2_19_cost_routing_input(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Routing Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->complete->handle($biz->id, 'routing test', 'gemini-pro');
        $this->assertNotNull($res['call_id']);
    }

    /**
     * [G2-27] the plan's mechanism is grounding + lexicon + the teaching box (P-097); a per-tenant fine-tune is an owner question
     * [G5-22] split: the key is C-Ai's; the MRR-discount half is KILLED (T591 — no discounts)
     */
    public function test_g5_22_api_key_vault_and_no_discounts(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Key Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $account = DB::table('ai_provider_accounts')->insertGetId([
            'business_id' => $biz->id,
            'provider_name' => 'openai',
            'api_key_ref' => 'vault:openai:key1',
            'is_active' => true,
            'health_status' => 'healthy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $account);
    }

    /**
     * [G5-44] cost is measured cent-precision in ai_calls
     */
    public function test_g5_44_cent_precision_cost(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cent Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->complete->handle($biz->id, 'cost check', rawCostCents: 15);
        $this->assertEquals(15, $res['cost_cents']);
    }

    /**
     * [G5-45] NAME COLLISION — X-150 ProviderWaterfall is the DATA-provider waterfall; model failover is C-Ai's
     */
    public function test_g5_45_model_failover_in_c_ai(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Failover Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->complete->handle(
            businessId: $biz->id,
            prompt: 'failover test',
            modelRequested: 'model-a',
            backupModel: 'model-b',
            simulatedTtftMs: 2000
        );

        $this->assertEquals('model-b', $res['model_served']);
    }

    /**
     * [G10-22] TTFT demotion in the model waterfall
     */
    public function test_g10_22_ttft_demotion(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'TTFT Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->complete->handle($biz->id, 'ttft test', simulatedTtftMs: 900);
        $this->assertGreaterThan(0, $res['call_id']);
    }

    public function test_model_board_renders_seeded_call_and_handles_retry(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Board Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $task = AiTask::create([
            'business_id' => $biz->id,
            'task_name' => 'instant_response',
            'max_ttft_ms' => 600,
            'cost_limit_cents' => 500,
        ]);

        $call = AiCall::factory()->create([
            'business_id' => $biz->id,
            'task_id' => $task->id,
            'model_served' => 'mock-gpt-4',
            'ttft_ms' => 420,
        ]);

        $initialCount = AiCall::where('business_id', $biz->id)->count();

        $component = Livewire::test(ModelBoard::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('mock-gpt-4')
            ->assertSee('420ms TTFT')
            ->call('retry', $call->id);

        $this->assertNull($component->get('errorMessage'));

        $newCount = AiCall::where('business_id', $biz->id)->count();
        $this->assertEquals($initialCount + 1, $newCount);
    }

    public function test_ai_embed_resolves_dimensions_from_registry(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Embed Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res1 = $this->embed->handle($biz->id, 'some text');
        $this->assertEquals(AiModel::TextEmbedding3Small->apiModelId(), $res1['model']);
        $this->assertEquals(AiModel::TextEmbedding3Small->embeddingDimensions(), $res1['dimensions']);
        $this->assertCount(AiModel::TextEmbedding3Small->embeddingDimensions(), $res1['embedding']);

        $res2 = $this->embed->handle($biz->id, 'some text', 'a-different-model');
        $this->assertEquals('a-different-model', $res2['model']);
        $this->assertNotEquals($res1['model'], $res2['model']);
    }
}
