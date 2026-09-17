<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Actions\ArApplyLateFeeAction;
use App\Modules\X211\Actions\ArLogOfflinePaymentAction;
use App\Modules\X211\Actions\ArSetLateFeeTermAction;
use App\Modules\X211\Domain\FeeAtCapException;
use App\Modules\X211\Domain\FeeWithoutTermException;
use App\Modules\X211\Domain\UnreferencedPaymentException;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ArPlanTerm;
use App\Modules\X211\Models\OfflinePayment;
use App\Modules\X211\Models\ReceivableState;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Overdue invoices'])]
class AgeingByReason extends Component
{
    public array $reference = [];

    public array $amountCents = [];

    public array $paymentMethod = [];

    public array $term = [];

    public array $feeCents = [];

    public ?string $refused = null;

    public ?string $refusedHeading = null;

    public ?string $error = null;

    public ?string $success = null;

    public function saveTerm(ArSetLateFeeTermAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->refused = null;
        $this->refusedHeading = null;
        $percent = (int) ($this->term['percent'] ?? 0);
        $cap = trim((string) ($this->term['cap'] ?? '')) === '' ? null : (int) $this->term['cap'];
        if ($percent < 1 || $percent > 100) {
            $this->error = 'Enter a late-fee percent between 1 and 100.';

            return;
        }
        if ($cap !== null && $cap < 1) {
            $this->error = 'The cap is an amount in cents, or blank for none.';

            return;
        }
        try {
            $terms = $action->handle(Tenancy::idOrFail(), $percent, $cap);
            $this->success = sprintf('Late-fee term saved: %d%% of the invoice%s.', $terms->late_fee_percent, $terms->late_fee_cap_cents === null ? ', no cap' : ', capped at '.number_format($terms->late_fee_cap_cents / 100, 2));
            $this->term = [];
        } catch (\Throwable $e) {
            $this->error = 'We could not save that term: '.$e->getMessage();
        }
    }

    public function applyLateFee(int $invoiceId, ArApplyLateFeeAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->refused = null;
        $this->refusedHeading = null;
        $businessId = Tenancy::idOrFail();
        $fee = (int) ($this->feeCents[$invoiceId] ?? 0);
        if ($fee <= 0) {
            $this->error = 'Enter the late fee in cents.';

            return;
        }
        try {
            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);
            $res = $action->handle($businessId, $invoiceId, $fee);
            $this->success = sprintf('Late fee of %s applied to %s.', number_format($res['applied_fee_cents'] / 100, 2), $invoice->invoice_number);
            unset($this->feeCents[$invoiceId]);
        } catch (FeeWithoutTermException|FeeAtCapException $e) {
            $this->refusedHeading = 'Late fee not applied';
            $this->refused = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not apply that fee: '.$e->getMessage();
        }
    }

    public function logPayment(int $invoiceId, ArLogOfflinePaymentAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->refused = null;
        $this->refusedHeading = null;
        $businessId = Tenancy::idOrFail();

        if (empty($this->reference[$invoiceId])) {
            $this->error = 'We need the reference number. This screen cannot take a photo yet, so a reference is the only way to log this payment.';

            return;
        }

        $amount = (int) ($this->amountCents[$invoiceId] ?? 0);
        if ($amount <= 0) {
            $this->error = 'Enter the amount that was paid.';

            return;
        }

        $method = (string) ($this->paymentMethod[$invoiceId] ?? 'check');
        if (! in_array($method, OfflinePayment::METHODS, true)) {
            $this->error = 'Choose how the payment arrived: cash, cheque, Zelle or wire.';

            return;
        }

        try {
            $ref = $this->reference[$invoiceId];

            app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);

            $payment = $action->handle($businessId, $invoiceId, $amount, $method, $ref);
            $this->success = sprintf('Payment logged: %s.', number_format($payment->amount_cents / 100, 2));
            unset($this->reference[$invoiceId], $this->amountCents[$invoiceId], $this->paymentMethod[$invoiceId]);
        } catch (UnreferencedPaymentException $e) {
            $this->refusedHeading = 'Payment not logged';
            $this->refused = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not log that payment: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $bizId = Tenancy::idOrFail();

        $terms = ArPlanTerm::where('business_id', $bizId)->first();

        $invoices = app(InvoiceReader::class)->unpaidOverdueForBusiness($bizId);

        $groups = [];
        $noReason = [];

        foreach ($invoices as $inv) {
            $inv->days_overdue = (int) $inv->due_date->diffInDays(today());
            $inv->late_fee_cents = (int) (ReceivableState::where('business_id', $bizId)->where('invoice_id', $inv->id)->value('late_fee_cents') ?? 0);
            $inv->balance_cents = $inv->total_cents - $inv->paid_cents;
            $inv->reason = ArDunningAction::where('business_id', $bizId)
                ->where('invoice_id', $inv->id)
                ->latest('id')
                ->value('reason') ?? 'No reason recorded yet';

            if ($inv->reason !== 'No reason recorded yet') {
                $groups[$inv->reason][] = $inv;
            } else {
                $noReason[] = $inv;
            }
        }

        ksort($groups);

        if (! empty($noReason)) {
            $groups['No reason recorded yet'] = $noReason;
        }

        return view('x-211::ageing-by-reason', [
            'groups' => $groups,
            'terms' => $terms,
        ]);
    }
}
