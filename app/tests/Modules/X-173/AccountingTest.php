<?php

namespace Tests\Modules\X173;

use App\Modules\X173\Domain\AccountingSyncEngine;
use PHPUnit\Framework\TestCase;

class AccountingTest extends TestCase
{
    /**
     * @group N-069
     * @group N-070
     * @group N-071
     * @group G1-03
     * @group G1-77
     * @group G15-13
     * @group G15-22
     * @group G15-25
     * @group G15-26
     */
    public function test_capabilities_are_enforced_for_accounting()
    {
        $engine = new AccountingSyncEngine();

        $lowResult = $engine->inferCategory('Desc', 0.62, 'Unknown Guess');
        $this->assertSame('uncategorised', $lowResult['assigned_category'], 'never to a guessed code');
        $this->assertTrue($lowResult['flagged_for_review']);
        $this->assertTrue($lowResult['is_low_confidence']);
        $this->assertSame(0.62, $lowResult['confidence_score']);

        $exactResult = $engine->inferCategory('Desc', 0.85, 'Known Category');
        $this->assertSame('Known Category', $exactResult['assigned_category']);
        $this->assertFalse($exactResult['flagged_for_review']);
        $this->assertFalse($exactResult['is_low_confidence']);
        $this->assertSame(0.85, $exactResult['confidence_score']);

        $boundaryResult = $engine->inferCategory('Desc', 0.8499, 'Another Guess');
        $this->assertSame('uncategorised', $boundaryResult['assigned_category']);
        $this->assertTrue($boundaryResult['flagged_for_review']);
        $this->assertTrue($boundaryResult['is_low_confidence']);
        $this->assertSame(0.8499, $boundaryResult['confidence_score']);
    }
}
