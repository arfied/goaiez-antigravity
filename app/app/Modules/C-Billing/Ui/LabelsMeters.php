<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

trait LabelsMeters
{
    /**
     * The owner-facing name for a meter type. Both vocabularies the schema
     * documents are covered; an unmapped type is shown as it was written, so a
     * meter this map has never seen still renders with a name rather than blank.
     *
     * @return array<string, string>
     */
    protected function meterLabels(): array
    {
        return [
            'sms' => 'SMS Segments',
            'sms_segments' => 'SMS Segments',
            'voice' => 'Voice Minutes',
            'voice_minutes' => 'Voice Minutes',
            'ai' => 'AI',
            'ai_seconds' => 'AI Seconds',
            'email' => 'Email',
            'lead' => 'Lead Credits',
        ];
    }
}
