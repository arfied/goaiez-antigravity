<?php

declare(strict_types=1);

namespace App\Modules\X211\Console;

use App\Models\User;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X198\Actions\PaymentReadAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Domain\NoResolutionAttemptException;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class EvidenceRecoveryCommand extends Command
{
    protected $signature = 'x211:evidence-recovery {--business= : Reuse this tenant instead of provisioning a new one}';

    protected $description = 'Evidence a real recovery outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        InvoiceEngine $invoiceEngine,
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

        // ⚠️ **`provision()` IS THE SIGNUP PATH AND IT SPENDS A PHONE NUMBER.**
        // It mints a new business on every call and claims a dedicated number out
        // of the platform pool, which nothing returns unless the tenant departs.
        // Five evidence commands called it on every run and the nine-number pool
        // is now empty, so this artifact could not be regenerated at all. The
        // tenant is therefore GIVEN — by --business, else by this artifact's own
        // record of the last one — and provisioned only when there is no other.
        $businessId = (int) ($this->option('business') ?: 0);

        if ($businessId === 0) {
            $previous = storage_path('app/evidence/X-211/recovery.json');

            if (File::exists($previous)) {
                $decoded = json_decode(File::get($previous), true);
                $businessId = is_array($decoded) ? (int) ($decoded['business_id'] ?? 0) : 0;
            }
        }

        if ($businessId === 0) {
            $businessId = $provisioner->provision($user)->id;
        }

        Tenancy::set($businessId);

        $personId = app(PersonLookupAction::class)->create($businessId, [
            'first_name' => 'Recovery',
            'last_name' => 'Customer',
            'phone' => '+15555555555',
        ]);

        $lines = [
            ['description' => 'Test Line', 'quantity' => 1, 'unit_price_cents' => 15000],
        ];
        $invoiceRes = $invoiceEngine->issueInvoice($businessId, $personId, $lines);
        $invoice = $invoiceRes['invoice'];

        $invoice->update(['due_date' => now()->subDays(5)]);

        $arEngine->recordReason($businessId, $invoice->id, 'card_expired');
        $plan = $arEngine->offerPlan($businessId, $invoice->id, 3, 'monthly');

        $invoice2Res = $invoiceEngine->issueInvoice($businessId, $personId, $lines);
        $invoice2 = $invoice2Res['invoice'];

        $refusedWithoutResolution = false;
        try {
            $arEngine->packageForCollections($businessId, $invoice2->id, $user->id);
        } catch (NoResolutionAttemptException $e) {
            $refusedWithoutResolution = true;
        }

        $data = [
            'business_id' => $businessId,
            'payments_written' => app(PaymentReadAction::class)->countForBusiness($businessId),
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

        return self::SUCCESS;
    }
}
