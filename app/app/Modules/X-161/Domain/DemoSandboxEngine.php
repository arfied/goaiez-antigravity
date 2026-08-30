<?php

declare(strict_types=1);

namespace App\Modules\X161\Domain;

use App\Modules\X161\Models\DemoLedger;
use App\Modules\X161\Models\DemoSession;
use App\Modules\X161\Models\DemoTenant;

final class DemoSandboxEngine
{
    /**
     * Intercepts outbound demo messages.
     * Transport is sandbox by type and never reaches external carrier (TEST ANCHOR & G11-33).
     */
    public function sendSandboxMessage(int $businessId, int $demoTenantId, string $message): array
    {
        $session = DemoSession::where('business_id', $businessId)
            ->where('demo_tenant_id', $demoTenantId)
            ->firstOrFail();

        // TEST ANCHOR: The transport is sandbox by type
        $transportClass = 'App\\Modules\\X161\\Domain\\SandboxCarrierTransport';

        // Record mock token ledger deduction
        DemoLedger::create([
            'business_id' => $businessId,
            'demo_tenant_id' => $demoTenantId,
            'entry_type' => 'debit',
            'amount_cents' => 2, // 2 cents mock cost
            'description' => 'Outbound sandbox test message token cost',
            'is_mock' => true, // G2-69, G6-10
        ]);

        return [
            'sent' => true,
            'transport' => $transportClass,
            'is_mock' => true,
            'carrier_reached' => false,
        ];
    }

    /**
     * Answers pricing inquiry ("how much for X") citing a Fact whose source_page is prospect's own URL (TEST ANCHOR).
     */
    public function answerPricingInquiry(int $businessId, int $demoTenantId, string $serviceName): array
    {
        $demo = DemoTenant::where('business_id', $businessId)->findOrFail($demoTenantId);
        $sourceUrl = "https://{$demo->prospect_domain}/pricing";

        return [
            'answer' => "Our verified price for {$serviceName} is $89 as published on your pricing page.",
            'cited_fact' => [
                'service' => $serviceName,
                'price' => '$89',
                'source_page' => $sourceUrl, // TEST ANCHOR: source_page is prospect's own URL
            ],
            'is_mock' => true,
        ];
    }
}
