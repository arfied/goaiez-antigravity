<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Actions\ArLogOfflinePaymentAction;
use App\Modules\X211\Models\ArDunningAction;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class AgeingByReason extends Component
{
    public array $reference = [];

    public array $amountCents = [];

    public ?string $error = null;

    public ?string $success = null;

    public function logPayment(int $invoiceId, ArLogOfflinePaymentAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $businessId = Tenancy::idOrFail();

        if (empty($this->reference[$invoiceId])) {
            $this->error = 'We need a reference number or a photo.';

            return;
        }

        $amount = (int) ($this->amountCents[$invoiceId] ?? 0);
        if ($amount <= 0) {
            $this->error = 'Enter the amount that was paid.';

            return;
        }

        try {
            $ref = $this->reference[$invoiceId];

            Invoice::where('business_id', $businessId)->findOrFail($invoiceId);

            $action->handle($businessId, $invoiceId, $amount, 'check', $ref);
            $this->success = 'Payment logged.';
            unset($this->reference[$invoiceId], $this->amountCents[$invoiceId]);
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

        $invoices = Invoice::where('business_id', $bizId)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->get();

        $groups = [];
        $noReason = [];

        foreach ($invoices as $inv) {
            $inv->days_overdue = (int) $inv->due_date->diffInDays(today());
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
        ]);
    }
}
