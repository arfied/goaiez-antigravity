<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Models\Payment;
use Livewire\Component;
use App\Support\Tenancy;

class Declines extends Component
{
    public bool $showAll = false;

    public array $hiddenRows = [];

    public function sendPayLink(int $paymentId, PaymentLinkAction $action): void
    {
        $payment = Payment::where('business_id', Tenancy::id())->findOrFail($paymentId);
        $result = $action->handle(Tenancy::id(), $payment->amount_cents, 'Payment for declined transaction');

        // Maybe we just hide the row after sending link? Or add a message.
        // It says "records nothing new: it hides the row for this session" for Settle up later.
        // For sendPayLink, maybe we don't hide, just do nothing visible except maybe a flash?
    }

    public function settleUpLater(int $paymentId): void
    {
        $this->hiddenRows[] = $paymentId;
    }

    public function toggleShowAll(): void
    {
        $this->showAll = ! $this->showAll;
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $query = Payment::where('business_id', Tenancy::id())
            ->where('status', 'failed')
            ->whereNotIn('id', $this->hiddenRows)
            ->orderByDesc('created_at');

        if (! $this->showAll) {
            $query->where('created_at', '>=', now()->startOfWeek());
        }

        $declines = $query->get();

        $recoveredCounts = 0;
        foreach ($declines as $decline) {
            $recovered = Payment::where('business_id', Tenancy::id())
                ->where('status', 'captured')
                ->where('payment_token', $decline->payment_token)
                ->where('created_at', '>', $decline->created_at)
                ->orderBy('created_at')
                ->first();

            $decline->recovered = $recovered;
            if ($recovered) {
                $recoveredCounts++;
            }
        }

        return view('x-199::declines', [
            'declines' => $declines,
            'declinesCount' => $declines->count(),
            'recoveredCount' => $recoveredCounts,
        ]);
    }
}
