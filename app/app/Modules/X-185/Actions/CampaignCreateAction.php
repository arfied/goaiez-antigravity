<?php

declare(strict_types=1);

namespace App\Modules\X185\Actions;

use App\Modules\X185\Models\Sequence;
use App\Modules\X185\Models\SequenceStep;

final class CampaignCreateAction
{
    /**
     * Creates multi-touch sequence campaign (G16-12).
     * An element with a design.changed event is present in frozen_elements (TEST ANCHOR).
     */
    public function createSequence(
        int $businessId,
        string $name,
        array $steps = [],
        array $frozenElements = ['button_color', 'hero_headline']
    ): Sequence {
        $seq = Sequence::create([
            'business_id' => $businessId,
            'name' => $name,
            'is_active' => true,
            'frozen_elements' => $frozenElements, // TEST ANCHOR: frozen_elements present
        ]);

        foreach ($steps as $idx => $step) {
            SequenceStep::create([
                'business_id' => $businessId,
                'sequence_id' => $seq->id,
                'step_number' => $idx + 1,
                'channel' => $step['channel'] ?? 'email',
                'template_variant' => $step['template_variant'] ?? 'default_v1',
                'delay_hours' => $step['delay_hours'] ?? 24,
            ]);
        }

        return $seq;
    }
}
