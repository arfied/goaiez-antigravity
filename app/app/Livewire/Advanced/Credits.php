<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Enums\CreditProduct;
use App\Models\AutoTopUpArrangement;
use App\Models\CreditLedgerEntry;
use App\Services\Billing\AutoTopUps;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use App\Support\Money;
use App\Support\PlanPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Credits extends Component
{
    private function currency(DefaultsRegistry $registry): string
    {
        $stored = $registry->value('billing.currency');

        return is_string($stored) && $stored !== '' ? $stored : 'USD';
    }

    public function render(CreditLedger $ledger, AutoTopUps $topUps, DefaultsRegistry $registry): View
    {
        $entries = CreditLedgerEntry::query()
            ->latest('id')
            ->take(25)
            ->get();

        $balances = [];
        $currency = $this->currency($registry);

        foreach (CreditProduct::cases() as $product) {
            $balance = $ledger->spendableBalance($product);
            $balances[$product->value] = [
                'name' => $product->name,
                'formatted' => $product === CreditProduct::Ai
                    ? PlanPricing::format(Money::of((int) round($balance / 100), $currency))
                    : number_format($balance),
            ];
        }

        $arrangement = AutoTopUpArrangement::query()->whereNull('cancelled_at')->first();
        $arrangementStatus = null;
        $arrangementThreshold = null;
        $arrangementAmount = null;

        if ($arrangement) {
            $arrangementStatus = $topUps->stoppedCause($arrangement) ?? $topUps->refusalFor($arrangement) ?? 'Active';
            $arrangementAmount = PlanPricing::format($arrangement->agreedAmount());
            $thresholdVal = $registry->int($arrangement->product->autoTopUpThresholdKey());
            $arrangementThreshold = $arrangement->product === CreditProduct::Ai
                ? PlanPricing::format(Money::of((int) round($thresholdVal / 100), $currency))
                : number_format($thresholdVal);
        }

        $mappedEntries = [];
        foreach ($entries as $entry) {
            $isMoney = $entry->product === CreditProduct::Ai;

            if ($isMoney) {
                $cents = (int) round($entry->delta / 100);
                $formattedAmount = PlanPricing::format(Money::of($cents, $currency));
                $balanceCents = (int) round($entry->balance_after / 100);
                $formattedBalance = PlanPricing::format(Money::of($balanceCents, $currency));
            } else {
                $formattedAmount = number_format($entry->delta);
                $formattedBalance = number_format($entry->balance_after);
            }

            $mappedEntries[] = [
                'date' => $entry->created_at?->translatedFormat('j M Y, H:i') ?? '',
                'type' => ucfirst($entry->kind->value),
                'description' => $entry->reason ?? '—',
                'amount' => $formattedAmount,
                'balance_after' => $formattedBalance,
                'product' => $entry->product->name,
            ];
        }

        return view('livewire.advanced.credits', [
            'entries' => $entries,
            'mappedEntries' => $mappedEntries,
            'balances' => $balances,
            'arrangement' => $arrangement,
            'arrangementStatus' => $arrangementStatus,
            'arrangementThreshold' => $arrangementThreshold,
            'arrangementAmount' => $arrangementAmount,
        ]);
    }
}
