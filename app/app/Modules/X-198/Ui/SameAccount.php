<?php

declare(strict_types=1);

namespace App\Modules\X198\Ui;

use App\Modules\X198\Actions\PaymentAttachAction;
use App\Modules\X198\Domain\PaymentAlreadyLandedException;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Same account'])]
class SameAccount extends Component
{
    public ?string $error = null;

    public ?string $success = null;

    public ?string $waiting = null;

    public function attach(int $paymentId, int $connectionId, PaymentAttachAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = null;
        try {
            $payment = $action->handle(Tenancy::idOrFail(), $paymentId, $connectionId);
            $conn = MerchantConnection::where('business_id', Tenancy::idOrFail())->findOrFail($connectionId);
            $this->success = sprintf('%s is now recorded against %s. Nothing was moved: this only records which merchant account the payment belongs to.', number_format($payment->amount_cents / 100, 2), $conn->merchant_account_id);
        } catch (PaymentAlreadyLandedException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That payment isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not attach that payment: '.$e->getMessage();
        }
    }

    public function pull($connectionId = null): void
    {
        $connectionId = (int) $connectionId;
        $this->error = null;
        $this->success = null;
        $this->waiting = null;
        try {
            $conn = MerchantConnection::where('business_id', Tenancy::idOrFail())->findOrFail($connectionId);
            $this->waiting = sprintf('Pulling payouts from %s waits on its payout read path; nothing was pulled and nothing changed.', $conn->gateway_name);
        } catch (ModelNotFoundException) {
            $this->error = "That connection isn't in this account any more.";
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $connections = MerchantConnection::where('business_id', $businessId)->orderBy('id')->get();
        // A declined attempt leaves a durable 'failed' row (GatewayEngine:153) and no money moved,
        // so it is not a payment recorded anywhere. awaiting_processor stays in: the label
        // says recorded, not settled, and the gateway does hold it.
        $payments = Payment::where('business_id', $businessId)->where('status', '!=', 'failed')->orderByDesc('id')->get();
        $payouts = Payout::where('business_id', $businessId)->get();
        $runs = ReconciliationRun::where('business_id', $businessId)->orderByDesc('id')->get();

        foreach ($connections as $conn) {
            $connPayments = $payments->where('merchant_connection_id', $conn->id);
            $byCurrency = $connPayments
                ->groupBy('currency')
                ->map(fn ($rows) => $rows->sum('amount_cents'))
                ->sortKeys()
                ->map(fn ($cents, $code) => number_format($cents / 100, 2).' '.$code)
                ->values()
                ->all();
            $conn->payments_line = implode(' · ', array_merge([$connPayments->count().' payments'], $byCurrency));

            $connPayouts = $payouts->where('merchant_connection_id', $conn->id);
            $conn->payouts_count = $connPayouts->count();
            $conn->payouts_cents = $connPayouts->sum('amount_cents');

            $payoutIds = $connPayouts->pluck('id');
            $latestRun = $runs->whereIn('payout_id', $payoutIds)->first();

            if ($latestRun === null) {
                $conn->last_reconciliation = 'never reconciled';
            } elseif ($latestRun->status === 'balanced') {
                $conn->last_reconciliation = 'balanced';
            } elseif ($latestRun->status === 'discrepancy_logged') {
                $conn->last_reconciliation = 'discrepancy';
            } else {
                $conn->last_reconciliation = 'never reconciled';
            }
        }

        $detached = $payments->whereNull('merchant_connection_id');

        return view('x-198::same-account', [
            'connections' => $connections,
            'detached' => $detached,
        ]);
    }
}
