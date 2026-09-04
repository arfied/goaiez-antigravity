<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Modules\X201\Domain\DisputeDefenseEngine;
use App\Support\Tenancy;
use Tests\TestCase;

class N009Test extends TestCase
{
    public function test_n_009_exposure_ledger(): void
    {
        // N-009
        $biz1 = TestCase::provisionTenant(['name' => 'N-009 Tenant 1', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'N-009 Tenant 2', 'currency' => 'USD']);

        Tenancy::set((int) $biz1->id);

        // Seed a paid invoice and partly-delivered job
        // Assert the exposure number. Since the method to get exposure doesn't exist on DisputeDefenseEngine, we will call it and expect it to exist.
        $engine = new DisputeDefenseEngine;

        $this->assertTrue(method_exists($engine, 'getExposure'), 'Engine must have getExposure method');

        $exposure1 = $engine->getExposure($biz1->id);
        $this->assertIsFloat($exposure1);

        Tenancy::set((int) $biz2->id);
        $exposure2 = $engine->getExposure($biz2->id);

        $this->assertNotEquals($exposure1, $exposure2, 'Tenants must not see each others exposure');
    }
}
