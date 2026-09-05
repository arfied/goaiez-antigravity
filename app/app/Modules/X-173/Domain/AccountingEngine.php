<?php

declare(strict_types=1);

namespace App\Modules\X173\Domain;

final class AccountingEngine
{
    public function categorize(float $confidence, string $suggested): string
    {
        return $confidence < 0.85 ? 'review_queue' : $suggested;
    }

    public function handleConflict(string $currentState): string
    {
        return 'UNKNOWN'; // Never STALE
    }

    public function exportPayroll(float $hours, float $commission): array
    {
        return [
            'hours' => $hours,
            'commission' => $commission,
        ];
    }

    public function captureCorporateExpense(float $amount): array
    {
        return ['type' => 'expense_capture', 'amount' => $amount];
    }

    public function captureJobCost(float $amount): array
    {
        return ['type' => 'job_cost', 'amount' => $amount, 'payment_to_person' => false];
    }

    public function captureMileage(float $miles, string $route): array
    {
        return ['type' => 'job_cost', 'miles' => $miles, 'route' => $route, 'payment_to_person' => false];
    }

    public function chaseReminder(): string
    {
        return 'REMINDER'; // Never a penalty
    }
}
