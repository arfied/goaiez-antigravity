<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X191\Models\LinkPitch;
use App\Modules\X191\Models\LinkPlacement;
use App\Modules\X191\Models\LinkTarget;

class X191Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-191';
    }

    public function fill(Business $business): int
    {
        if (LinkPlacement::where('business_id', $business->id)->where('anchor_text', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $target = LinkTarget::create([
            'business_id' => $business->id,
            'domain' => self::MARKER.'partner-blog.example',
            'target_url' => 'https://partner-blog.example/',
        ]);

        $pitch1 = LinkPitch::create([
            'business_id' => $business->id,
            'target_id' => $target->id,
            'pitch_body' => self::MARKER.'Broken link on your resources page',
            'page_specific_fact' => self::MARKER.'resources page links to a dead PDF',
            'sent_month' => '2026-09',
            'is_sent' => true,
            'follow_up_count' => 0,
        ]);

        $pitch2 = LinkPitch::create([
            'business_id' => $business->id,
            'target_id' => $target->id,
            'pitch_body' => self::MARKER.'Follow-up on the resources page',
            'page_specific_fact' => self::MARKER.'resources page links to a dead PDF',
            'sent_month' => '2026-09',
            'is_sent' => true,
            'follow_up_count' => 0,
        ]);

        $pitch3 = LinkPitch::create([
            'business_id' => $business->id,
            'target_id' => $target->id,
            'pitch_body' => self::MARKER.'Guide swap for your tools list',
            'page_specific_fact' => self::MARKER.'resources page links to a dead PDF',
            'sent_month' => '2026-09',
            'is_sent' => true,
            'follow_up_count' => 0,
        ]);

        LinkPlacement::create([
            'business_id' => $business->id,
            'pitch_id' => $pitch1->id,
            'placed_url' => 'https://partner-blog.example/resources',
            'anchor_text' => self::MARKER.'local plumbing guide',
            'is_active' => true,
        ]);

        LinkPlacement::create([
            'business_id' => $business->id,
            'pitch_id' => $pitch3->id,
            'placed_url' => 'https://partner-blog.example/tools',
            'anchor_text' => self::MARKER.'water heater checklist',
            'is_active' => false,
        ]);

        return 5;
    }

    public function purge(Business $business): int
    {
        $placementsCount = LinkPlacement::where('business_id', $business->id)
            ->where('anchor_text', 'like', self::MARKER.'%')
            ->delete();

        $pitchesCount = LinkPitch::where('business_id', $business->id)
            ->where('pitch_body', 'like', self::MARKER.'%')
            ->delete();

        $targetsCount = LinkTarget::where('business_id', $business->id)
            ->where('domain', 'like', self::MARKER.'%')
            ->delete();

        return $placementsCount + $pitchesCount + $targetsCount;
    }
}
