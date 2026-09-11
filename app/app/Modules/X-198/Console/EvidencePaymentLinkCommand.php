<?php

declare(strict_types=1);

namespace App\Modules\X198\Console;

use App\Models\User;
use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Models\Payment;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class EvidencePaymentLinkCommand extends Command
{
    protected $signature = 'x198:evidence-payment-link {--business= : Reuse this tenant instead of provisioning a new one}';

    protected $description = 'Evidence a real pay link outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        PaymentLinkAction $paymentLinkAction
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
            $previous = storage_path('app/evidence/x198/payment-link.json');

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

        $payment = Payment::create([
            'business_id' => $businessId,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'payment_token' => 'tok_failed',
            'idempotency_key' => 'idem_pay_link_'.time(),
            'status' => 'failed',
        ]);

        $link = $paymentLinkAction->handle($businessId, $payment->id);

        $data = [
            'business_id' => $businessId,
            'provider_link_id' => $link->provider_link_id,
            'url' => $link->url,
            'currency' => $payment->currency,
            'amount_cents' => $payment->amount_cents,
            'payment_id' => $payment->id,
            'database' => config('database.connections.'.config('database.default').'.database', 'goaiez_antig_money'),
            'running_unit_tests' => app()->runningUnitTests(),
            'created_at' => now()->toIso8601String(),
            'command' => 'php artisan x198:evidence-payment-link',
        ];

        $path = storage_path('app/evidence/x198/payment-link.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info($link->url);

        return self::SUCCESS;
    }
}
