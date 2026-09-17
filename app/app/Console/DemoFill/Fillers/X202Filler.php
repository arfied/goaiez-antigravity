<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X202\Models\ApprovalItem;

class X202Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-202';
    }

    public function fill(Business $business): int
    {
        if (ApprovalItem::where('business_id', $business->id)->where('subject', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        ApprovalItem::create([
            'business_id' => $business->id,
            'item_type' => 'refund_proposal',
            'subject' => 'demo·Refund for the Ridgeline callback',
            'payload' => ['amount_cents' => 12500, 'reason' => 'callback within 30 days'],
            'expires_at' => now()->addDays(3),
        ]);

        ApprovalItem::create([
            'business_id' => $business->id,
            'item_type' => 'renewal_clause',
            'subject' => 'demo·Renewal with an ambiguous clause',
            'payload' => ['clause' => 'auto-renew', 'term_months' => 12],
            'expires_at' => now()->addDays(3),
            'status' => 'approved',
            'decided_at' => now()->subDay(),
            'decision_comment' => "Approved — the clause matches last year's.",
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return ApprovalItem::where('business_id', $business->id)->where('subject', 'like', self::MARKER.'%')->delete();
    }
}
