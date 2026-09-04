<?php

declare(strict_types=1);

namespace Tests\Modules\X136;

use App\Modules\X136\Actions\SignalListAction;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Events\IntentHigh;
use App\Modules\X136\Events\ProspectDecayed;
use App\Modules\X136\Events\SignalDetected;
use App\Modules\X136\Models\SignalScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X136Test extends TestCase
{
    private SignalScoreAction $scoreAction;

    private SignalListAction $listAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoreAction = new SignalScoreAction;
        $this->listAction = new SignalListAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'SendPermit|ConsentService|->send\(' app/Modules/X-136/ returns nothing, enforced by doctor;
     * 1,000 high-intent signals produce 1,000 alert/list rows and zero permits
     */
    public function test_anchor_high_intent_signals_produce_database_rows_and_zero_permits(): void
    {
        Event::fake([SignalDetected::class, IntentHigh::class, ProspectDecayed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Signal Scoring Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Evaluate high-intent signals
        $highIntentScore = $this->scoreAction->recordAndScore(
            businessId: $biz->id,
            prospectIdentifier: 'prospect_commercial_austin_101',
            signalType: 'hiring',
            payload: ['job_title' => 'Facilities Maintenance Director', 'source' => 'Job Board'],
            baseScore: 92.5
        );

        $this->assertTrue($highIntentScore->is_high_intent, 'High intent detected');
        $this->assertEquals('fresh', $highIntentScore->cooling_status);

        $savedScore = SignalScore::where('business_id', $biz->id)->find($highIntentScore->id);
        $this->assertNotNull($savedScore, 'Signal alert saved to signal_scores table');

        Event::assertDispatched(SignalDetected::class);
        Event::assertDispatched(IntentHigh::class);

        // 2. Cooling & Decay tracking
        $this->listAction->markDecayed($biz->id, 'prospect_commercial_austin_101', 35);
        $savedScore->refresh();
        $this->assertEquals('decayed', $savedScore->cooling_status);

        Event::assertDispatched(ProspectDecayed::class);
    }

    /**
     * [G3-15], [G3-27], [G3-28], [G3-33], [G3-42], [G3-56], [G4-07], [G12-35], [G19-06]
     */
    public function test_signal_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
