<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Destinations\InviteOption;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Would you post that where other people can see it?"
 *
 * `17` FPR-04's email channel — the second of the three delivery routes it
 * names, and the first one to exist. Slice F built the on-screen redirect;
 * `BUILD-PLAN` §2.6.4 conflict 2 recorded the other two as waiting on services
 * that did not exist. This is email's turn; SMS is still waiting on 10DLC.
 *
 * ⚠️ **IT IS TRANSACTIONAL, AND THAT IS A CONDITION RATHER THAN A LABEL.**
 * `OutreachPurpose::Transactional`'s own docblock spells out the trap: `24` §3.3
 * permits review requests *because* they "stay strictly transactional: no
 * offers, no incentives, no promotional language" — so **a review invite
 * carrying a discount code has made itself marketing, and nothing in the enum
 * can notice**. The composer owns that boundary, this is the composer, and the
 * copy below is the boundary. Anything added here that sells something moves
 * this message under the Do Not Call registries and rule 7's prior-express-
 * written-consent requirement, retroactively, for every send.
 *
 * ⚠️ **NO INCENTIVE OF ANY KIND, EVER** — `29` §2's first rule and `CLAUDE.md`'s
 * flat prohibition. Not a discount, not a prize draw, not "we'll enter you". An
 * incentivised review is a fake review with extra steps, and it is a Google and
 * Trustpilot terms violation before it is a rule of ours.
 *
 * ⚠️ **EVERY LINK IS OUR OWN REDIRECT, NEVER THE PLATFORM'S.** The URLs come
 * from `ReviewInvites::offerFor()`, which builds `feedback.destination` routes —
 * decision 387's reasoning, unchanged by the channel: resolving the platform URL
 * on the far side is what keeps `linkFor()`'s host allowlist on every hand-off
 * and what makes the click row exist at all. Pasting a Google URL into an email
 * template would lose the click, the allowlist and decision 308's derived link
 * in one edit, and the `ArchitectureTest` lint on destination hosts covers
 * `app/` and `resources/views/`, which is where a template would be.
 */
final class ReviewInviteEmail extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * ⛔ **COMMERCIAL UNDER CAN-SPAM AND `Transactional` UNDER THE TCPA, AND
     * BOTH ARE CORRECT** (T176 P21, decision 4018). The docblock above is
     * emphatic that this message is transactional, and it is — under `24` §3.3
     * and `OutreachPurpose`, which answer *may this be sent without prior
     * express written consent*. CAN-SPAM asks a different question: §7702(2)(A)
     * is a **primary purpose** test, and the primary purpose of asking a
     * stranger to publish a public endorsement is the promotion of a business's
     * service. That the message carries no offer is what keeps the *TCPA*
     * answer; it does not move this one.
     *
     * ⚠️ **THE COST OF BEING WRONG IS ASYMMETRIC AND THAT IS WHY THIS IS THE
     * ANSWER.** Classing it commercial costs an unsubscribe link and three
     * lines of footer on a message the recipient is under no obligation to
     * want. Classing it transactional and being wrong costs a statutory
     * penalty per message, on the highest-volume customer-facing send in the
     * product.
     *
     * ⚠️ **AND THE UNSUBSCRIBE IS SOMETHING THIS RECIPIENT ACTUALLY NEEDS.**
     * They are a tenant's customer, they hold no account with us, and until
     * this slice the only way they could stop these was to reply STOP to a text
     * they may never have been sent. `29` §2's own rule — every rating is
     * captured and none suppressed — is about *ratings*, not about mail; it
     * costs nothing here.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::Commercial;
    }

    /**
     * @param  list<InviteOption>  $options
     */
    public function __construct(
        private readonly string $businessName,
        private readonly array $options,
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
        $message = (new MailMessage)
            ->subject("Thanks for your feedback about {$this->businessName}")
            ->greeting('Thank you')
            ->line(
                "You left {$this->businessName} some feedback just now, and they were glad to get it."
            )
            // ⚠️ "If you have a moment" and "would help" — an invitation that can
            // be declined without the person feeling they owe anybody anything.
            // `22`'s outcome language, and the honest register a transactional
            // message has to keep: no urgency, no scarcity, nothing that reads
            // as a campaign.
            ->line('If you have a moment, posting it publicly would help other people find them.');

        // ⚠️ The first option is the primary action and the rest are plain
        // links. `MailMessage` renders exactly one button, so a second
        // `->action()` silently replaces the first — which would ship an email
        // offering three destinations and linking to one.
        $primary = $this->options[0] ?? null;

        if ($primary instanceof InviteOption) {
            $message->action($primary->label(), $primary->url);
        }

        foreach (array_slice($this->options, 1) as $option) {
            $message->line("{$option->label()}: {$option->url}");
        }

        return $message->line(
            'If you would rather not, that is completely fine — your feedback already reached them.'
        );
    }
}
