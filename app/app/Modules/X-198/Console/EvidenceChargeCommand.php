<?php

declare(strict_types=1);

namespace App\Modules\X198\Console;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class EvidenceChargeCommand extends Command
{
    protected $signature = 'x198:evidence-charge';

    protected $description = 'Evidence a real charge outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        InvoiceEngine $invoiceEngine,
        GatewayEngine $gatewayEngine
    ): int {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $user = User::first() ?? User::factory()->create();
        $tenant = $provisioner->provision($user);
        Tenancy::set($tenant->id);
        $businessId = $tenant->id;

        $person = Person::create([
            'business_id' => $businessId,
            'first_name' => 'Evidence',
            'last_name' => 'Customer',
            'phone' => '+15555555555',
        ]);

        $lines = [
            ['description' => 'Test Line', 'quantity' => 1, 'unit_price_cents' => 12500],
        ];
        $invoiceRes = $invoiceEngine->issueInvoice($businessId, $person->id, $lines);
        $invoice = $invoiceRes['invoice'];

        $gatewayEngine->connect($businessId, 'stripe', 'acct_tenant_stripe_123'); // Is this ok? Wait, is stripe connected to our platform?
        $payment = $gatewayEngine->capture($businessId, 12500, 'tok_visa', 'idem_j9_'.time());

        $invoiceEngine->recordPayment($businessId, $invoice->id, 12500);

        $data = [
            'gateway_charge_id' => $payment->gateway_charge_id,
            'invoice_id' => $invoice->id,
            'invoice_status' => 'paid',
            'payment_status' => $payment->status,
            'amount_cents' => 12500,
            'database' => config('database.connections.' . config('database.default') . '.database', 'goaiez_antig_money'),
            'running_unit_tests' => false,
            'captured_at' => now()->toIso8601String(),
            'command' => 'php artisan x198:evidence-charge',
        ];

        $path = storage_path('app/evidence/j9/charge.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info($payment->gateway_charge_id);

        return self::SUCCESS;
    }
}
