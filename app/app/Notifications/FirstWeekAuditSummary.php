<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We found N things worth fixing." Day 0 of the First 7-Day Results Path
 * (`28` §3.2).
 *
 * ⚠️ **"FOUND", NEVER "FOUND AND FIXED" — §3.2's OWN WORDING IS NARROWED HERE.**
 * The doc's table reads "we found and fixed N things", and this application has
 * no record of which of an audit's findings any actuation has actually acted on
 * by the time this sends. Claiming a fix that has not happened is exactly the
 * modelled-number failure `28` §3.3's integrity rule exists to forbid, one row
 * up from proof numbers rather than inside them. `findings` is a plain count of
 * what the pre-signup audit turned up — real, and already true the moment the
 * tenant signed up.
 *
 * ⚠️ **THE COUNT COMES FROM `wizard_progress.data['audit']`, COPIED AND FROZEN
 * AT PROVISIONING** (`TenantProvisioner::prefill()`), **NEVER FROM A LIVE
 * RE-READ.** `public_audits` prunes at 90 days and this can fire well inside
 * that window, but re-deriving from the live row would be the same mistake
 * `TenantProvisioner`'s own docblock already refused for the wizard's pre-fill:
 * a foreign key from inside the tenant boundary to a table that deletes on a
 * schedule. `FirstWeekPath` reads the frozen copy for the same reason.
 */
final class FirstWeekAuditSummary extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(v) — findings about the recipient's own website, produced by
     * the service they bought. The output of the product is not an
     * advertisement for it.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(private readonly int $findings) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('We found '.$this->findingsPhrase().' worth fixing')
            ->greeting('Welcome — here is what we already know');

        return $message
            ->line("Before you signed up, we looked at your listing and found {$this->findingsPhrase()} worth fixing.")
            ->line('That is where we are starting.');
    }

    private function findingsPhrase(): string
    {
        return $this->findings === 1 ? 'one thing' : "{$this->findings} things";
    }
}
