<?php

declare(strict_types=1);

namespace App\Modules\X199\Console;

use App\Models\User;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X198\Actions\PaymentReadAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class EvidenceInvoiceCommand extends Command
{
    protected $signature = 'x199:evidence-invoice {--business= : Reuse this tenant instead of provisioning a new one}';

    protected $description = 'Evidence a real invoice payment outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        InvoiceEngine $invoiceEngine
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

        // ⚠️ **`provision()` IS THE SIGNUP PATH AND IT SPENDS A PHONE NUMBER.**
        // It mints a new business on every call and claims a dedicated number out
        // of the platform pool, which nothing returns unless the tenant departs.
        // Five evidence commands called it on every run and the nine-number pool
        // is now empty, so this artifact could not be regenerated at all. The
        // tenant is therefore GIVEN — by --business, else by this artifact's own
        // record of the last one — and provisioned only when there is no other.
        $businessId = (int) ($this->option('business') ?: 0);

        if ($businessId === 0) {
            $previous = storage_path('app/evidence/X-199/invoice.json');

            if (File::exists($previous)) {
                $decoded = json_decode(File::get($previous), true);
                $businessId = is_array($decoded) ? (int) ($decoded['business_id'] ?? 0) : 0;
            }
        }

        if ($businessId === 0) {
            $user = User::first() ?? User::factory()->create();
            $businessId = $provisioner->provision($user)->id;
        }

        Tenancy::set($businessId);

        $personId = app(PersonLookupAction::class)->create($businessId, [
            'first_name' => 'Invoice',
            'last_name' => 'Customer',
            'phone' => '+15555555555',
        ]);

        $lines = [
            ['description' => 'Test Line', 'quantity' => 1, 'unit_price_cents' => 12500],
        ];
        $invoiceRes = $invoiceEngine->issueInvoice($businessId, $personId, $lines);
        $invoice = $invoiceRes['invoice'];

        if (preg_match('/^INV-[0-9]{6}$/', (string) $invoice->invoice_number) !== 1) {
            $this->error('Invoice number is not this module sequence');

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
            'business_id' => $businessId,
            'payments_written' => app(PaymentReadAction::class)->countForBusiness($businessId),
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

        return self::SUCCESS;
    }
}
