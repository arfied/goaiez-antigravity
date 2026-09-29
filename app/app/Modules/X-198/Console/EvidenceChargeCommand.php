<?php

declare(strict_types=1);

namespace App\Modules\X198\Console;

use App\Models\User;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Models\Payment;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class EvidenceChargeCommand extends Command
{
    protected $signature = 'x198:evidence-charge {--business= : Reuse this tenant instead of provisioning a new one}';

    protected $description = 'Evidence a real charge outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        GatewayEngine $gatewayEngine
    ): int {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

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
            $previous = storage_path('app/evidence/j9/charge.json');

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

        $gatewayEngine->connect($businessId, 'stripe', 'acct_tenant_stripe_123');
        $payment = $gatewayEngine->capture($businessId, 12500, 'tok_visa', 'idem_j9_'.time());

        $data = [
            'business_id' => $businessId,
            'tenant_payments_total' => Payment::where('business_id', $businessId)->count(),
            'gateway_charge_id' => is_array($payment) ? null : $payment->gateway_charge_id,
            'payment_status' => is_array($payment) ? $payment['status'] : $payment->status,
            'amount_cents' => 12500,
            'database' => config('database.connections.'.config('database.default').'.database', 'goaiez_antig_money'),
            'running_unit_tests' => app()->runningUnitTests(),
            'captured_at' => now()->toIso8601String(),
            'command' => 'php artisan x198:evidence-charge',
        ];

        $path = storage_path('app/evidence/j9/charge.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info(is_array($payment) ? 'refused' : $payment->gateway_charge_id);

        return self::SUCCESS;
    }
}
