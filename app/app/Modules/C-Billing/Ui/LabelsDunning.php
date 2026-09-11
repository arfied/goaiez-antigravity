<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

trait LabelsDunning
{
    /**
     * The words a business owner reads when a screen groups cases by stage, or
     * when a case is drawn alone. The first is written as 'active' but rendered
     * as 'no stage recorded' so the empty state prints something that acts like a
     * name rather than blank.
     *
     * These name the STAGE and never the services. The columns the day-21 token
     * summarises are read by nothing, so a label saying the AI is off would claim
     * an act nobody performs; the revenue-recovery screen already prints those
     * three columns, with the caveat that nothing applies them.
     *
     * @return array<string, string>
     */
    protected function dunningLabels(): array
    {
        return [
            'active' => 'no stage recorded',
            'warning' => 'warning stage',
            'banner' => 'banner stage',
            'ai_off_voicemail_only' => 'final stage',
        ];
    }

    /**
     * The signal a stage carries. Unmapped falls to 'unknown', which is what the
     * pill exists to render for a value we have not measured.
     *
     * @return array<string, string>
     */
    protected function dunningPillStates(): array
    {
        return [
            'warning' => 'attention',
            'banner' => 'attention',
            'ai_off_voicemail_only' => 'alert',
        ];
    }
}
