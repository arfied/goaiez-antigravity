<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\PaymentLink;
use App\Modules\X199\Actions\DeferDeclineAction;
use App\Modules\X199\Models\DeclineDeferral;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Payment Declines & Exceptions'])]
class Declines extends Component
{
    public bool $showAll = false;

    public ?string $error = null;

    public ?string $errorHeading = null;

    public function sendPayLink(int $paymentId, PaymentLinkAction $action): void
    {
        $this->error = null;
        $this->errorHeading = 'Could not make that pay link';
        try {
            $action->handle(Tenancy::idOrFail(), $paymentId);
        } catch (ModelNotFoundException) {
            $this->error = "That attempt isn't in this account any more — reload the list.";
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function settleUpLater(int $paymentId, DeferDeclineAction $action): void
    {
        $this->error = null;
        $this->errorHeading = 'Could not set that aside';
        try {
            $action->handle(Tenancy::idOrFail(), $paymentId);
        } catch (ModelNotFoundException) {
            $this->error = "That attempt isn't in this account any more — reload the list.";
        }
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
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! $this->showAll) {
            $query->where('created_at', '>=', now()->startOfWeek());

            $deferredPaymentIds = DeclineDeferral::where('business_id', Tenancy::id())
                ->pluck('payment_id');
            $query->whereNotIn('id', $deferredPaymentIds);
        }

        $declines = $query->get();
        $declineIds = $declines->pluck('id');

        $paymentLinks = PaymentLink::where('business_id', Tenancy::id())
            ->whereIn('payment_id', $declineIds)
            ->get()
            ->keyBy('payment_id');

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

            $decline->deferred = DeclineDeferral::where('business_id', Tenancy::id())
                ->where('payment_id', $decline->id)
                ->exists();

            $decline->pay_link = $paymentLinks->get($decline->id);
        }

        return view('x-199::declines', [
            'declines' => $declines,
            'declinesCount' => $declines->count(),
            'recoveredCount' => $recoveredCounts,
        ]);
    }
}
