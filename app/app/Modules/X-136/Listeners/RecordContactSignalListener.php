<?php

declare(strict_types=1);

namespace App\Modules\X136\Listeners;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Models\Signal;

final class RecordContactSignalListener
{
    public const SIGNAL_TYPE = 'contact.created';

    public const BASE_SCORE = 50.0;

    public function __construct(private readonly SignalScoreAction $score) {}

    public function handle(ContactCreated $event): void
    {
        $prospect = 'person:'.$event->personId;

        $already = Signal::query()
            ->where('business_id', $event->businessId)
            ->where('prospect_identifier', $prospect)
            ->where('signal_type', self::SIGNAL_TYPE)
            ->exists();

        if ($already) {
            return;
        }

        $this->score->recordAndScore(
            $event->businessId,
            $prospect,
            self::SIGNAL_TYPE,
            ['name' => $event->name, 'has_phone' => $event->phone !== null, 'has_email' => $event->email !== null],
            self::BASE_SCORE,
        );
    }
}
