<?php
namespace Tests\Modules\X180;
use Tests\TestCase;
use App\Modules\X180\Domain\PackEngine;
class X180Test extends TestCase {
    public function test_capabilities() {
        $engine = new PackEngine();
        $this->assertTrue($engine->ensureAdPacksNotFenced(['type' => 'ad_pack']));
        $this->assertTrue($engine->verifyClaim('Best service', 'Source 1'));
    }
}
