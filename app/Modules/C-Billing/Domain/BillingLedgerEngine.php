<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Domain;

use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\DunningState;
use App\Modules\CBilling\Models\TrialLimit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class BillingLedgerEngine
{
    /**
     * Debit ledger with atomic balance update and hundredths-of-a-cent precision (TEST ANCHOR, G1-18, G1-20).
     */
    public function debit(
        int $businessId,
        int $amountHundredthsCents,
        string $referenceId,
        string $description
    ): CreditLedgerEntry {
        return DB::transaction(function () use ($businessId, $amountHundredthsCents, $referenceId, $description) {
            $limit = TrialLimit::where('business_id', $businessId)->lockForUpdate()->first();
            if ($limit === null) {
                throw new \DomainException('REFUSAL: Ledger not found');
            }

            $newBalance = $limit->current_balance_hundredths_cents - $amountHundredthsCents;
            if ($newBalance < 0) {
                throw new \DomainException('REFUSAL: Insufficient balance');
            }

            $limit->update(['current_balance_hundredths_cents' => $newBalance]);

            return CreditLedgerEntry::create([
                'business_id' => $businessId,
                'entry_type' => 'debit',
                'amount_hundredths_cents' => $amountHundredthsCents,
                'balance_after_hundredths_cents' => $newBalance,
                'reference_id' => $referenceId,
                'description' => $description,
                // App clock, not the column default: Mrr and RevenueRecovery filter this column against now().
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Grant credits to ledger (integer hundredths of a cent).
     */
    public function grant(
        int $businessId,
        int $amountHundredthsCents,
        string $referenceId,
        string $description
    ): CreditLedgerEntry {
        return DB::transaction(function () use ($businessId, $amountHundredthsCents, $referenceId, $description) {
            $limit = TrialLimit::where('business_id', $businessId)->lockForUpdate()->first();
            if ($limit === null) {
                $limit = TrialLimit::create([
                    'business_id' => $businessId,
                    'current_balance_hundredths_cents' => 0,
                ]);
            }

            $newBalance = $limit->current_balance_hundredths_cents + $amountHundredthsCents;
            $limit->update(['current_balance_hundredths_cents' => $newBalance]);

            return CreditLedgerEntry::create([
                'business_id' => $businessId,
                'entry_type' => 'grant',
                'amount_hundredths_cents' => $amountHundredthsCents,
                'balance_after_hundredths_cents' => $newBalance,
                'reference_id' => $referenceId,
                'description' => $description,
                // App clock, not the column default: Mrr and RevenueRecovery filter this column against now().
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Top-up charge with daily top-up ceiling enforcement (G1-13, G1-14).
     */
    public function topup(int $businessId, int $amountCents): array
    {
        return DB::transaction(function () use ($businessId, $amountCents) {
            $limit = TrialLimit::where('business_id', $businessId)->lockForUpdate()->first();
            if ($limit === null) {
                $limit = TrialLimit::create([
                    'business_id' => $businessId,
                    'daily_topup_ceiling_cents' => 50000,
                ]);
            }

            $today = Carbon::today()->toDateString();
            if ($limit->last_topup_date && $limit->last_topup_date->toDateString() !== $today) {
                $limit->topups_today_cents = 0;
            }

            if (($limit->topups_today_cents + $amountCents) > $limit->daily_topup_ceiling_cents) {
                throw new \DomainException('REFUSAL: Daily top-up ceiling exceeded');
            }

            $limit->topups_today_cents += $amountCents;
            $limit->last_topup_date = Carbon::today();
            $hundredths = $amountCents * 100;
            $limit->current_balance_hundredths_cents += $hundredths;
            $limit->save();

            $entry = CreditLedgerEntry::create([
                'business_id' => $businessId,
                'entry_type' => 'topup',
                'amount_hundredths_cents' => $hundredths,
                'balance_after_hundredths_cents' => $limit->current_balance_hundredths_cents,
                'reference_id' => 'topup_'.uniqid(),
                'description' => 'Automatic balance top-up',
                // App clock, not the column default: Mrr and RevenueRecovery filter this column against now().
                'created_at' => now(),
            ]);

            return [
                'status' => 'charged',
                'charged_amount_cents' => $amountCents,
                'new_balance_hundredths_cents' => $limit->current_balance_hundredths_cents,
                'entry_id' => $entry->id,
            ];
        });
    }

    /**
     * Advance 21-day dunning ladder (TEST ANCHOR, G7-01, G11-13, G1-19, G1-28).
     */
    public function advanceDunning(int $businessId, int $dayInCycle): DunningState
    {
        return DB::transaction(function () use ($businessId, $dayInCycle) {
            $state = DunningState::where('business_id', $businessId)->first();
            if ($state === null) {
                $state = DunningState::create([
                    'business_id' => $businessId,
                    'day_in_cycle' => $dayInCycle,
                ]);
            }

            $status = match (true) {
                $dayInCycle < 10 => 'warning',
                $dayInCycle < 21 => 'banner',
                default => 'ai_off_voicemail_only',
            };

            // At day 21: AI off, phone answering still true, voicemail only (TEST ANCHOR & P-095)
            $aiEnabled = ($dayInCycle < 21);
            $phoneAnswering = true; // phone KEEPS ANSWERING always (G1-19, G1-28)
            $voicemailOnly = ($dayInCycle >= 21);

            $state->update([
                'day_in_cycle' => $dayInCycle,
                'status' => $status,
                'ai_enabled' => $aiEnabled,
                'phone_answering' => $phoneAnswering,
                'voicemail_only' => $voicemailOnly,
            ]);

            return $state;
        });
    }

    /**
     * 8:1 derivation over cent-precision true cost (G7-15, G13-03, G1-57).
     */
    public function calculateRetailDebit(int $rawCostHundredthsCents, int $multiplier = 8): int
    {
        return $rawCostHundredthsCents * $multiplier;
    }
}
