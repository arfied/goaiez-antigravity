<?php

declare(strict_types=1);

namespace App\Modules\X136\Listeners;

use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Models\Signal;
use App\Modules\X155\Events\FormCaptured;

final class RecordFormSignalListener
{
    public const SIGNAL_TYPE = 'form.submitted';

    public const BASE_SCORE = SignalScoreAction::HIGH_INTENT_SCORE;

    public function __construct(private readonly SignalScoreAction $score) {}

    public function handle(FormCaptured $event): void
    {
        $prospect = 'person:'.$event->personId;

        $already = Signal::query()
            ->where('business_id', $event->businessId)
            ->where('signal_type', self::SIGNAL_TYPE)
            ->where('payload->submission_id', $event->submissionId)
            ->exists();

        if ($already) {
            return;
        }

        $this->score->recordAndScore(
            $event->businessId,
            $prospect,
            self::SIGNAL_TYPE,
            ['submission_id' => $event->submissionId, 'form_definition_id' => $event->formDefinitionId],
            self::BASE_SCORE,
        );
    }
}
