<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Models\Invoice;
use App\Modules\CAi\Domain\AiEngine; // Assuming CAi is the AI module

final class DraftInvoiceFromJobAction
{
    public function __construct(
        private readonly InvoiceDraftAction $draftAction,
        private readonly mixed $aiEngine = null // Mockable AI Engine
    ) {}

    public function handle(int $businessId, int $customerId, int $totalCents, string $jobDescription): Invoice
    {
        $lines = [];
        
        try {
            // [G1-05] AI structures lines from the job (R245 decision: attempt AI first)
            if ($this->aiEngine) {
                $lines = $this->aiEngine->structureInvoiceLines($jobDescription, $totalCents);
            } else {
                throw new \RuntimeException('AI Engine unavailable');
            }
        } catch (\Throwable $e) {
            // [G1-05] fails one line at the total
            $lines = [
                [
                    'description' => 'Work order completion - ' . substr($jobDescription, 0, 50),
                    'quantity' => 1,
                    'unit_price_cents' => $totalCents
                ]
            ];
        }

        return $this->draftAction->handle($businessId, $customerId, $lines);
    }
}
