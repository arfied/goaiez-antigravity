<?php

declare(strict_types=1);

namespace Tests\Modules\X161;

use App\Modules\X161\Actions\DemoConvertAction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoConvertActionGateTest extends TestCase
{
    public function test_conversion_refused_on_expired_or_missing_session(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Gate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $action = new DemoConvertAction;

        // Missing or expired demo tenant ID (e.g. 999)
        $result = $action->convertToLive(
            businessId: $biz->id,
            demoTenantId: 999,
            liveBusinessId: 444
        );

        $this->assertIsArray($result);
        $this->assertSame('refused', $result['status']);
        $this->assertSame('DEMO_NOT_FOUND', $result['refusal_code']);
        $this->assertSame('We could not find your demo session. It may have expired.', $result['reason']);
    }
}
