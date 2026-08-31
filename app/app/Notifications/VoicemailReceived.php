<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Enums\VoicemailAudioState;
use App\Http\Controllers\Account\VoicemailRecordingController;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Voicemail owner notify — somebody rang and left a message.
 *
 * ## ⛔ NO NUMBER, NO TRANSCRIPT, NO RECORDING LINK
 *
 * Each absence is deliberate and each has a different reason:
 *
 *   - **No caller number.** Email is not a channel this application controls
 *     once it is sent: it is forwarded, quoted, indexed by the recipient's own
 *     provider and held in backups nobody here can reach. A member of the
 *     public's mobile number belongs on the owner's own screen, behind their
 *     login, under the tenant boundary — which is where `Account\Calls` shows it.
 *   - **No transcript.** It is a stranger's speech, machine-transcribed, and it
 *     may be PHI — `VoicemailTranscribed`'s own warning, in a slice where the
 *     tenant may have no BAA. It also may not exist yet: this mail fires on the
 *     audio and never waits for words.
 *   - **No recording link.** A signed URL in an email is a link anybody who sees
 *     the email can open, which is exactly why `VoicemailRecorded` forbids
 *     carrying a vendor URL in the first place.
 *
 * What the mail carries is the fact and the way in: *somebody left you a
 * message, it is on your calls page.* That is the whole of it, and it is enough
 * to make an owner act.
 *
 * ⛔ **AND THAT SENTENCE ONLY BECAME TRUE ON 2026-08-16** (4519). When this mail
 * was written there was no route, no controller and no player anywhere in the
 * application — *"the recording is on your calls page"* named a place that did
 * not exist, while the bytes sat in object storage with no reader at all. The
 * page now has an `<audio>` element behind `auth` and the tenant boundary
 * ({@see VoicemailRecordingController}), which is
 * also why this mail still carries no link: the control is the session, and a
 * link in a mailbox is not one.
 *
 * ⚠️ **AND IT SAYS WHEN THERE IS NO AUDIO**, because an owner who opens the page
 * expecting a recording and finds none would reasonably conclude the feature is
 * broken. A voicemail whose recording we could not retrieve is still a missed
 * customer, and the honest sentence is better than a silent one.
 */
final class VoicemailReceived extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(
        private readonly ?string $audioAvailable = null,
        private readonly ?int $seconds = null,
    ) {}

    /**
     * §7702(17)(A)(v) — a report about the recipient's own account, delivering
     * the service they bought. It advertises nothing. `FirstWeekSummary` carries
     * the argument in full.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // ⚠️ **MAIL ONLY.** `NotifyOwnerOfVoicemailJob`'s docblock records the
        // three separate reasons R7's "and/or SMS" half is not built.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Somebody left you a message')
            ->greeting('A caller left you a voicemail');

        if ($this->audioAvailable === VoicemailAudioState::Stored->value) {
            $message->line($this->seconds === null
                ? 'The recording is on your calls page.'
                : "The recording is on your calls page — {$this->seconds} seconds.");
        } else {
            $message->line(
                'We could not save the recording this time, so the call is on your calls page '
                .'with the caller\'s number and nothing else. Ringing them back is the fastest way '
                .'to find out what they wanted.'
            );
        }

        return $message->action('Open your calls', route('account.calls'));
    }

    /**
     * ⛔ **NO CALLER, NO WORDS, NO PATH.** This array reaches the database
     * notification store.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['audio_state' => $this->audioAvailable];
    }
}
