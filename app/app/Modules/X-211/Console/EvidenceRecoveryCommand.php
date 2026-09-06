<?php

declare(strict_types=1);

namespace App\Modules\X211\Console;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Domain\NoResolutionAttemptException;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class EvidenceRecoveryCommand extends Command
{
    protected $signature = 'x211:evidence-recovery';

    protected $description = 'Evidence a real recovery outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        InvoiceEngine $invoiceEngine,
        GatewayEngine $gatewayEngine,
        ArEngine $arEngine
    ): int {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $queueDriver = config('queue.default');
        if ($queueDriver === 'sync') {
            $this->error('UNRESOLVED — queue driver is sync in this checkout');
            return self::FAILURE;
        }

        $user = User::first() ?? User::factory()->create();
        $tenant = $provisioner->provision($user);
        Tenancy::set($tenant->id);
        $businessId = $tenant->id;

        $person = Person::create([
            'business_id' => $businessId,
            'first_name' => 'Recovery',
            'last_name' => 'Customer',
            'phone' => '+15555555555',
        ]);

        $lines = [
            ['description' => 'Test Line', 'quantity' => 1, 'unit_price_cents' => 15000],
        ];
        $invoiceRes = $invoiceEngine->issueInvoice($businessId, $person->id, $lines);
        $invoice = $invoiceRes['invoice'];

        $invoice->update(['due_date' => now()->subDays(5)]);

        $arEngine->recordReason($businessId, $invoice->id, 'card_expired');
        $plan = $arEngine->offerPlan($businessId, $invoice->id, 3, 'monthly');

        $gatewayEngine->connect($businessId, 'stripe', 'acct_tenant_stripe_123');
        $payment = $gatewayEngine->capture($businessId, $plan->installment_amount_cents, 'tok_visa', 'idem_x211_'.time());

        if (!str_starts_with((string)$payment->gateway_charge_id, 'ch_')) {
            $this->error('gateway_charge_id must start with ch_');
            return self::FAILURE;
        }

        $invoice2Res = $invoiceEngine->issueInvoice($businessId, $person->id, $lines);
        $invoice2 = $invoice2Res['invoice'];
        
        $refusedWithoutResolution = false;
        try {
            $arEngine->packageForCollections($businessId, $invoice2->id, $user->id);
        } catch (NoResolutionAttemptException $e) {
            $refusedWithoutResolution = true;
        }

        $data = [
            'gateway_charge_id' => $payment->gateway_charge_id,
            'plan_id' => $plan->id,
            'installment_amount_cents' => $plan->installment_amount_cents,
            'reason' => 'card_expired',
            'invoice_number' => $invoice->invoice_number,
            'refused_without_resolution' => $refusedWithoutResolution,
            'queue_driver' => $queueDriver,
            'database' => config('database.connections.'.config('database.default').'.database', 'goaiez_antig_money'),
            'running_unit_tests' => app()->runningUnitTests(),
            'captured_at' => now()->toIso8601String(),
            'command' => 'php artisan x211:evidence-recovery',
        ];

        $path = storage_path('app/evidence/X-211/recovery.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info($payment->gateway_charge_id);

        return self::SUCCESS;
    }
}
