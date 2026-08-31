<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Enums\FirstWeekWinType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Day 7's closing summary — proof numbers plus one sentence about the week's
 * win, whatever it was (`28` §3.2).
 *
 * ⚠️ **THE NUMBERS ARE WHATEVER `ProofNumbers::recompute()` JUST COMPUTED, AND
 * A ZERO RENDERS AS ZERO.** `28` §3.3's own integrity rule — "if a number would
 * be zero, show zero" — applies here exactly as it does on the Home screen this
 * path exists to make less lonely. This class never estimates: it renders
 * whatever three integers it is handed.
 *
 * ⚠️ **`$winType === null` IS A REAL, NAMEABLE OUTCOME, NOT A FAILURE TO
 * RENDER.** A tenant can reach day 7 having had no Google review and no
 * fallback link click recorded — the fallback email went out, nobody can prove
 * whether it was read — and this says that honestly rather than inventing a
 * win that did not happen.
 */
final class FirstWeekSummary extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(v) — a report of what the recipient's own account did in its
     * first week. It is the delivery of the service, not an advertisement for
     * it. See `FirstWeekImportPrompt` for the argument in full and for the one
     * on this list that is genuinely borderline.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(
        private readonly int $googleReviews,
        private readonly int $leads,
        private readonly int $recovered,
        private readonly ?FirstWeekWinType $winType,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your first week, in numbers')
            ->greeting('One week in')
            ->line("New Google reviews: {$this->googleReviews}")
            ->line("Leads captured: {$this->leads}")
            ->line("Unhappy customers recovered: {$this->recovered}")
            ->line($this->winSentence());
    }

    private function winSentence(): string
    {
        return match ($this->winType) {
            FirstWeekWinType::GoogleReview => 'The best part: a new customer told the world about you.',
            FirstWeekWinType::FallbackProof => 'We sent you your review link earlier this week — it is still there whenever you want it.',
            null => 'We are still working on your first win. It usually does not take long.',
        };
    }
}
