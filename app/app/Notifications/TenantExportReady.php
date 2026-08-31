<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your data download is ready" (`28` §3.7).
 *
 * ⚠️ **CONSTRUCTED FROM SCALARS, NOT FROM `TenantExport`** — `SupportSessionSummary`'s
 * reason, sharper here: `tenant_exports` is held to one writer/reader by a
 * chokepoint lint in `tests/Feature/Architecture/CrmTest.php`, and a notification
 * holding the model would be a second reader arrived at as a side effect of a
 * nicer constructor. `ExportBuilder` reads the row and hands over the one URL
 * this email needs.
 */
final class TenantExportReady extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(ii) — it completes a transaction the recipient initiated by
     * asking for their data. It is also how a data-portability request is
     * fulfilled, and a suppressible fulfilment is a request we failed to answer.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @param  bool  $forOneContact  Whether this build is `44` §10's per-contact
     *                               export rather than the whole account.
     *                               ⚠️ **NO DEFAULT, DELIBERATELY** (6572): the
     *                               two mails describe different disclosures,
     *                               and a defaulted argument is one a future
     *                               caller forgets — which sends *"everything we
     *                               have on file for your account"* about a file
     *                               holding one person, on the one email whose
     *                               subject somebody may forward.
     */
    public function __construct(
        private readonly string $downloadUrl,
        private readonly int $expiryDays,
        private readonly bool $forOneContact,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * ⚠️ **NO NAME AND NO CONTACT ID IN EITHER VERSION.** The per-contact
     * sentence says *a contact* rather than which one: an email is the least
     * controlled place this application writes anything down, and the owner
     * already knows whose profile they pressed the button on.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->forOneContact) {
            return (new MailMessage)
                ->subject('Your contact download is ready')
                ->greeting('Your download is ready')
                ->line('Everything you hold about one of your contacts is in one ZIP file, ready now.')
                ->line('Open the manifest inside it before you send it on — it says what is in the '
                    .'file, what is not, and what to check.')
                ->action('Download it', $this->downloadUrl)
                ->line("This link stops working in {$this->expiryDays} days.");
        }

        return (new MailMessage)
            ->subject('Your data download is ready')
            ->greeting('Your download is ready')
            ->line('Everything we have on file for your account is in one ZIP file, ready now.')
            ->action('Download my data', $this->downloadUrl)
            ->line("This link stops working in {$this->expiryDays} days.");
    }
}
