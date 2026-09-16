<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\GateResult;

class X183Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-183';
    }

    public function fill(Business $business): int
    {
        if (ContentDraft::where('business_id', $business->id)->where('title', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $d1 = ContentDraft::create(['business_id' => $business->id, 'title' => self::MARKER.'Spring Maintenance Guide', 'is_published' => true, 'body_text' => '...']);
        $d2 = ContentDraft::create(['business_id' => $business->id, 'title' => self::MARKER.'Emergency Leak Fixes', 'is_published' => false, 'body_text' => '...']);

        GateResult::create(['business_id' => $business->id, 'draft_id' => $d2->id, 'passed' => false, 'rejection_reason' => self::MARKER.'Missing citation', 'checked_at' => now()]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = GateResult::where('business_id', $business->id)->where('rejection_reason', 'like', self::MARKER.'%')->delete();
        $count += ContentDraft::where('business_id', $business->id)->where('title', 'like', self::MARKER.'%')->delete();
        return $count;
    }
}
