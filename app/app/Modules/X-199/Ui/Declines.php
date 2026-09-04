<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Models\Payment;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class Declines extends Component
{
    public bool $showAll = false;

    public array $hiddenRows = [];

    public array $payLinks = [];

    public ?string $error = null;

    public function sendPayLink(int $paymentId, PaymentLinkAction $action): void
    {
        $this->error = null;
        try {
            $payment = Payment::where('business_id', Tenancy::idOrFail())->findOrFail($paymentId);
            $result = $action->handle(Tenancy::idOrFail(), $payment->amount_cents, 'Payment for declined transaction');
            $this->payLinks[$paymentId] = $result['payment_url'];
        } catch (ModelNotFoundException) {
            $this->error = "That attempt isn't in this account any more — reload the list.";
        } catch (\Throwable $e) {
            $this->error = 'The pay link was not made: '.$e->getMessage();
        }
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
