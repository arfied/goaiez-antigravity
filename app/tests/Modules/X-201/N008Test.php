<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Modules\X201\Actions\DisputeRecordAction;
use App\Modules\X201\Domain\DisputeDefenseEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class N008Test extends TestCase
{
    public function test_n_008_gateway_agnostic(): void
    {
        // N-008
        $path = app_path('Modules/X-201');
        $files = File::allFiles($path);

        $gateways = ['stripe', 'authorize', 'braintree', 'square', 'paypal', 'adyen'];
        $foundGateways = [];

        foreach ($files as $file) {
            $content = strtolower(file_get_contents($file->getPathname()));
            foreach ($gateways as $gateway) {
                if (str_contains($content, $gateway)) {
                    $foundGateways[] = $gateway;
                }
            }
        }

        $this->assertEmpty($foundGateways, 'X-201 code should not contain gateway names');

        // Test that record action accepts an unknown gateway string.
        // Wait, the record action takes: (int $businessId, int $invoiceId, int $chargebackAmountCents, string $reason)
        // Let's pass a dummy gateway string if it allows it.
        // Currently it doesn't take a gateway parameter. If the test requires the event/action to accept an unknown string,
        // we assert it can be passed or we just call the action. The prompt says:
        // "and the record action accepts an event whose gateway field is an unknown string."
        // We will pass an unknown gateway to the record action (which would require the signature to be updated, but for now we just pass it if it accepts it).
        // Since we can't change the action, we'll try to pass it to the action or engine. Let's see if we can create an event class or something.
        // Actually, we can just instantiate the engine and pass a fake event if needed. But for now, just calling the action is fine.
        $engine = new DisputeDefenseEngine;
        $recordAction = new DisputeRecordAction($engine);

        $biz = TestCase::provisionTenant(['name' => 'N-008 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // The assertion is that it accepts a chargeback event (or action call) with an unknown gateway.
        // As the current action doesn't accept gateway, this is a named missing behaviour.
        $reflection = new \ReflectionMethod(DisputeRecordAction::class, 'handle');
        $params = array_map(fn ($p) => $p->getName(), $reflection->getParameters());

        $this->assertContains('gateway', $params, 'Record action must accept gateway field');

        // This will fail since gateway is not accepted.
        $dispute = $recordAction->handle($biz->id, 102, 20000, 'fraudulent', 'unknown_gateway_xyz');
        $this->assertEquals('opened', $dispute->status);
    }
}
