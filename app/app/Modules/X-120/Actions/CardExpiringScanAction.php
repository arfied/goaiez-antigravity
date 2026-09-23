<?php

declare(strict_types=1);

namespace App\Modules\X120\Actions;

use App\Modules\X120\Events\CardExpiring;
use App\Modules\X120\Models\CardToken;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class CardExpiringScanAction
{
    public const EXPIRING_WARNING_DAYS = 30;

    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function expiringWarningDays(): int
    {
        return $this->registry->int('billing.card.expiring_warning_days');
    }

    /**
     * Scans for expiring cards.
     * A card expiring in 20 days produces EXACTLY ONE alert, not a sequence (TEST ANCHOR).
     */
    public function scan(int $businessId, ?Carbon $currentDate = null): array
    {
        $now = $currentDate ?? Carbon::now();
        $cards = CardToken::where('business_id', $businessId)->get();

        $alerted = [];

        foreach ($cards as $card) {
            $cardExpDate = Carbon::createFromDate($card->exp_year, $card->exp_month, 1)->endOfMonth();
            $daysRemaining = (int) $now->diffInDays($cardExpDate, false);

            // If expiring within 30 days and alert NOT yet sent (TEST ANCHOR: exactly one alert, not a sequence)
            if ($daysRemaining >= 0 && $daysRemaining <= $this->expiringWarningDays() && ! $card->alert_sent) {
                $card->update(['alert_sent' => true]);

                Event::dispatch(new CardExpiring($businessId, $card->id, $daysRemaining));

                $alerted[] = [
                    'card_id' => $card->id,
                    'last_four' => $card->last_four,
                    'days_remaining' => $daysRemaining,
                ];
            }
        }

        return [
            'status' => 'scanned',
            'alerted_count' => count($alerted),
            'alerted_cards' => $alerted,
        ];
    }
}
