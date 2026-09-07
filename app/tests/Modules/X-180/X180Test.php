<?php

namespace Tests\Modules\X180;

use App\Modules\X180\Domain\PackEngine;
use Tests\TestCase;

class X180Test extends TestCase
{
    public function test_capabilities()
    {
        $engine = new PackEngine;
        $this->assertTrue($engine->ensureAdPacksNotFenced(['type' => 'ad_pack']));
        $this->assertTrue($engine->verifyClaim('Best service', 'Source 1'));
    }

    /**
     * [G10-10]
     */
    public function test_g10_10_ad_packs_not_fenced_and_claims_verifiable(): void
    {
        $engine = new PackEngine;

        $this->assertTrue($engine->ensureAdPacksNotFenced(['fenced' => false]));
        $this->assertTrue($engine->ensureAdPacksNotFenced(['type' => 'ad_pack']));
        
        $this->assertFalse($engine->ensureAdPacksNotFenced(['fenced' => true]));

        $this->assertTrue($engine->verifyClaim('claim text', 'Valid Source'));

        $this->assertFalse($engine->verifyClaim('claim text', ''));
    }
}
