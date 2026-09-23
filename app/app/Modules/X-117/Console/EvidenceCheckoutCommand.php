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

final class EvidenceCheckoutCommand extends Command
{
    protected $signature = 'x117:evidence-checkout {--business= : Reuse this tenant instead of provisioning a new one}';

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

        // ⚠️ **`provision()` IS THE SIGNUP PATH AND IT SPENDS A PHONE NUMBER.**
        // It mints a new business on every call and claims a dedicated number out
        // of the platform pool, which nothing returns unless the tenant departs.
        // Five evidence commands called it on every run and the nine-number pool
        // is now empty, so this artifact could not be regenerated at all. The
        // tenant is therefore GIVEN — by --business, else by this artifact's own
        // record of the last one — and provisioned only when there is no other.
        $businessId = (int) ($this->option('business') ?: 0);

        if ($businessId === 0) {
            $previous = storage_path('app/evidence/X-117/checkout.json');

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

        $checkoutRes = $checkoutEngine->checkoutCart($businessId, $sessionToken, 'auth_x117_'.time());

        $data = [
            'order_id' => $checkoutRes['order_id'],
            'business_id' => $businessId,
            'order_status' => $checkoutRes['status'],
            'merchant_connected' => $gatewayEngine->hasConnectedMerchant($businessId),
            'payments_written' => $gatewayEngine->paymentCount($businessId),
            'waiting_on' => 'a browser-side Stripe Elements / publishable-key card-entry surface (X-120 CardVault\'s, parked behind a contract)',
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

        $this->info("{$checkoutRes['order_id']} {$checkoutRes['status']}");

        return self::SUCCESS;
    }
}
