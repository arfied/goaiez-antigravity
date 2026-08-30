<?php

declare(strict_types=1);

namespace App\Modules\X161\Actions;

use App\Modules\X161\Events\DemoProvisioned;
use App\Modules\X161\Models\DemoLedger;
use App\Modules\X161\Models\DemoSession;
use App\Modules\X161\Models\DemoTenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class DemoProvisionAction
{
    /**
     * Provisions a sandbox demo tenant (G6-01, G11-33).
     * Every seeded row carries is_mock = true (G2-69, G6-10).
     */
    public function provisionDemo(int $businessId, string $prospectDomain): DemoTenant
    {
        $slug = 'demo-'.Str::slug($prospectDomain).'-'.Str::random(6);

        $demo = DemoTenant::create([
            'business_id' => $businessId,
            'demo_slug' => $slug,
            'prospect_domain' => $prospectDomain,
            'is_mock' => true, // G6-10
            'is_converted' => false,
        ]);

        // Create sandbox session
        DemoSession::create([
            'business_id' => $businessId,
            'demo_tenant_id' => $demo->id,
            'session_token' => Str::random(32),
            'transport_type' => 'sandbox', // TEST ANCHOR
            'is_mock' => true,
        ]);

        // Seed initial mock ledger credits
        DemoLedger::create([
            'business_id' => $businessId,
            'demo_tenant_id' => $demo->id,
            'entry_type' => 'credit',
            'amount_cents' => 5000, // $50 mock credits
            'description' => 'Initial Sandbox Demo Credit Allocation',
            'is_mock' => true,
        ]);

        Event::dispatch(new DemoProvisioned($businessId, $demo->id, $slug));

        return $demo;
    }
}
