<?php
namespace Tests\Modules\X183;
use Tests\TestCase;
use App\Modules\X183\Domain\GateEngine;
class X183Test extends TestCase {
    public function test_capabilities() {
        $engine = new GateEngine();
        $this->assertTrue($engine->requireDoubleConsent(true, true));
        $this->assertEquals('ApprovalDesk', $engine->escalateNegativeComment('this is bad'));
        $this->assertTrue($engine->requireRealData(true));
        $this->assertTrue($engine->prePublishGate(true, true));
        $this->assertTrue($engine->noSamplePrices('real price $10'));
    }
}
