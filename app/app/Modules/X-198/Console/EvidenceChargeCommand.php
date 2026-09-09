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
    protected $signature = 'x198:evidence-charge';

    protected $description = 'Evidence a real charge outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
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

        $gatewayEngine->connect($businessId, 'stripe', 'acct_tenant_stripe_123');
        $payment = $gatewayEngine->capture($businessId, 12500, 'tok_visa', 'idem_j9_'.time());

        $data = [
            'payments_written' => Payment::where('business_id', $businessId)->count(),
            'gateway_charge_id' => $payment->gateway_charge_id,
            'payment_status' => $payment->status,
            'amount_cents' => 12500,
            'database' => config('database.connections.'.config('database.default').'.database', 'goaiez_antig_money'),
            'running_unit_tests' => app()->runningUnitTests(),
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
