<?php

namespace Tests\Modules\X184;

use App\Modules\X184\Domain\PlanEngine;
use Tests\TestCase;

class X184Test extends TestCase
{
    public function test_capabilities()
    {
        $engine = new PlanEngine;
        $this->assertTrue($engine->approveCadenceOnly('cadence'));
        $this->assertFalse($engine->approveCadenceOnly('topic_list'));
        $this->assertTrue($engine->recommendNotAutoPost('recommend'));
        $this->assertTrue($engine->refreshOnFatigue(true));
    }
}
