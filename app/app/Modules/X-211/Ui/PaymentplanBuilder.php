<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Actions\ArOfferPlanAction;
use App\Modules\X211\Domain\PlanPastThresholdException;
use App\Modules\X211\Models\ArPlanTerm;
use App\Modules\X211\Models\PaymentPlan;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Payment plans'])]
class PaymentplanBuilder extends Component
{
    public array $installments = [];

    public array $frequency = [];

    public ?string $error = null;

    public ?string $success = null;

    public ?string $financing = null;

    public function offerPlan(int $invoiceId, ArOfferPlanAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->financing = null;
        $businessId = Tenancy::idOrFail();

        $count = (int) ($this->installments[$invoiceId] ?? 3);
        $frequency = (string) ($this->frequency[$invoiceId] ?? 'monthly');

        if ($count < 2) {
            $this->error = 'A plan is at least two payments.';

            return;
        }

        if (! in_array($frequency, ['weekly', 'biweekly', 'monthly'], true)) {
            $this->error = 'Pick monthly, every two weeks, or weekly.';

            return;
        }

        try {
            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);
            $plan = $action->handle($businessId, $invoiceId, $count, $frequency);
            $this->success = sprintf(
                'Plan recorded on %s: %d %s payments of %s. The customer has not been told: nothing in this module sends anything, so the offer waits on a way to reach them.',
                $invoice->invoice_number,
                $plan->installments_count,
                $plan->frequency,
                number_format($plan->installment_amount_cents / 100, 2)
            );
            unset($this->installments[$invoiceId], $this->frequency[$invoiceId]);
        } catch (PlanPastThresholdException $e) {
            $this->financing = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not offer that plan: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $bizId = Tenancy::idOrFail();

        $terms = ArPlanTerm::where('business_id', $bizId)->first() ?? new ArPlanTerm;

        $plans = PaymentPlan::where('business_id', $bizId)->latest('id')->get();
        $planned = $plans->pluck('invoice_id')->all();

        $invoices = app(InvoiceReader::class)->openUnpaidForBusiness($bizId, $planned);

        foreach ($invoices as $inv) {
            $inv->balance_cents = $inv->total_cents - $inv->paid_cents;
            $typed = (int) ($this->installments[$inv->id] ?? 3);
            if ($typed < 2) {
                $inv->preview_line = 'a plan is at least two payments';
            } else {
                $inv->preview_line = 'about '.number_format((int) ceil($inv->balance_cents / $typed) / 100, 2).' each';
            }
        }

        $numbers = app(InvoiceReader::class)->numbersById($bizId, $planned);

        return view('x-211::paymentplan-builder', [
            'terms' => $terms,
            'invoices' => $invoices,
            'plans' => $plans,
            'numbers' => $numbers,
        ]);
    }
}
