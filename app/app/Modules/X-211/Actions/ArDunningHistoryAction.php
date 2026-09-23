<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Models\ArDunningAction;

class ArDunningHistoryAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(int $businessId, int $invoiceId): array
    {
        $reasonsFlip = array_flip(ArEngine::REASONS);

        $actions = ArDunningAction::where('business_id', $businessId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('id', 'desc')
            ->get();

        return array_map(function ($action) use ($reasonsFlip) {
            return [
                'action' => $action->action,
                'reason_code' => $reasonsFlip[$action->reason] ?? $action->reason,
                'label' => $action->reason,
                'created_at' => $action->created_at ? $action->created_at->toIso8601String() : null,
            ];
        }, $actions->all());
    }
}
