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
    protected $signature = 'x198:evidence-payment-link';

    protected $description = 'Evidence a real pay link outside the suite';

    public function handle(
        TenantProvisioner $provisioner,
        PaymentLinkAction $paymentLinkAction
    ): int {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $user = User::first() ?? User::factory()->create();
        $tenant = $provisioner->provision($user);
        Tenancy::set($tenant->id);
        $businessId = $tenant->id;

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
