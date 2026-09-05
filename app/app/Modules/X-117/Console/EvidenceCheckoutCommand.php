<?php

declare(strict_types=1);

namespace App\Modules\X117\Console;

use App\Models\User;
use App\Modules\X117\Domain\CheckoutEngine;
use App\Modules\X117\Models\Sellable;
use App\Modules\X198\Domain\GatewayEngine;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class EvidenceCheckoutCommand extends Command
{
    protected $signature = 'x117:evidence-checkout';

    protected $description = 'Evidence a real checkout outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        CheckoutEngine $checkoutEngine,
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

        $sellable = Sellable::create([
            'business_id' => $businessId,
            'name' => 'Evidence Checkout Item',
            'sku' => 'EVIDENCE-1',
            'unit_price_cents' => 4500,
            'inventory_quantity' => 10,
        ]);

        $sessionToken = 'session_x117_test_'.time();
        $checkoutEngine->addToCart($businessId, $sessionToken, $sellable->id, 1);

        $gatewayEngine->connect($businessId, 'stripe', 'acct_tenant_stripe_123');
        $payment = $gatewayEngine->capture($businessId, 4500, 'tok_visa', 'idem_x117_'.time());

        $checkoutRes = $checkoutEngine->checkoutCart($businessId, $sessionToken, 'auth_x117_'.time());

        $data = [
            'gateway_charge_id' => $payment->gateway_charge_id,
            'order_id' => $checkoutRes['order_id'],
            'order_status' => $checkoutRes['status'],
            'amount_cents' => 4500,
            'queue_driver' => config('queue.default'),
            'database' => config('database.connections.'.config('database.default').'.database', 'goaiez_antig_money'),
            'running_unit_tests' => app()->runningUnitTests(),
            'captured_at' => now()->toIso8601String(),
            'command' => 'php artisan x117:evidence-checkout',
        ];

        $path = storage_path('app/evidence/X-117/checkout.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info($payment->gateway_charge_id);

        return self::SUCCESS;
    }
}
