<?php

declare(strict_types=1);

namespace App\Modules\X199\Console;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class EvidenceInvoiceCommand extends Command
{
    protected $signature = 'x199:evidence-invoice';

    protected $description = 'Evidence a real invoice payment outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        InvoiceEngine $invoiceEngine,
        GatewayEngine $gatewayEngine
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
            'first_name' => 'Invoice',
            'last_name' => 'Customer',
            'phone' => '+15555555555',
        ]);

        $lines = [
            ['description' => 'Test Line', 'quantity' => 1, 'unit_price_cents' => 12500],
        ];
        $invoiceRes = $invoiceEngine->issueInvoice($businessId, $person->id, $lines);
        $invoice = $invoiceRes['invoice'];

        if (preg_match('/^INV-[0-9]{6}$/', (string) $invoice->invoice_number) !== 1) {
            $this->error('Invoice number is not this module sequence');

            return self::FAILURE;
        }

        $gatewayEngine->connect($businessId, 'stripe', 'acct_tenant_stripe_123');
        $payment = $gatewayEngine->capture($businessId, 12500, 'tok_visa', 'idem_x199_'.time());

        if (! str_starts_with((string) $payment->gateway_charge_id, 'ch_')) {
            $this->error('gateway_charge_id must start with ch_');

            return self::FAILURE;
        }

        $invoiceEngine->recordPayment($businessId, $invoice->id, 12500);
        $invoice = $invoice->fresh();

        $duplicateRefusedBy = null;
        try {
            DB::table('invoices')->insert([
                'business_id' => $invoice->business_id,
                'customer_id' => $invoice->customer_id,
                'invoice_number' => $invoice->invoice_number,
                'total_cents' => $invoice->total_cents,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => $invoice->due_date,
                'pdf_url' => $invoice->pdf_url,
            ]);
            $this->error('Duplicate insert did not throw an exception');

            return self::FAILURE;
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'invoices_business_id_invoice_number_unique')) {
                $duplicateRefusedBy = 'invoices_business_id_invoice_number_unique';
            }
        }

        $data = [
            'gateway_charge_id' => $payment->gateway_charge_id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_status' => $invoice->status,
            'paid_at' => $invoice->paid_at,
            'total_cents' => $invoice->total_cents,
            'duplicate_refused_by' => $duplicateRefusedBy,
            'queue_driver' => $queueDriver,
            'database' => config('database.connections.'.config('database.default').'.database', 'goaiez_antig_money'),
            'running_unit_tests' => app()->runningUnitTests(),
            'captured_at' => now()->toIso8601String(),
            'command' => 'php artisan x199:evidence-invoice',
        ];

        $path = storage_path('app/evidence/X-199/invoice.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info($payment->gateway_charge_id);

        return self::SUCCESS;
    }
}
