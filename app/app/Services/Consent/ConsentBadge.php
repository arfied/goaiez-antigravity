<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;

/**
 * The consent badge (`34` §1.1 and §1.2) — one line saying where a contact
 * stands, in words rather than a dot.
 *
 * ⚠️ **BUILT ONLY BY `ConsentService::badgesFor()`, AND THAT IS THE WHOLE
 * DESIGN.** The list and the header both render this object from the same
 * method, so the two screens cannot disagree — and neither screen touches
 * `consent_records` or `suppression_list`, which each sit behind a chokepoint
 * lint (624's rule, applied before the N+1 made it tempting: `proofFor()` per
 * row is one query pair per contact, and a list page is twenty-five).
 *
 * ⚠️ **THE STOP HALF IS *STANDING* SUPPRESSION, NOT THE TRAIL** — and the
 * difference is a lift. The consent trail (`proofFor()`) has no entry type for
 * a lift, so a badge derived from it would keep saying "asked you to stop"
 * after the owner cleared their own Never-contact — directly beside the
 * Never-contact control saying the opposite. The standing question already has
 * one predicate (`ConsentService::standingEntryQuery()`'s docblock: one
 * predicate, two callers), and this is its third caller rather than a second
 * spelling.
 *
 * ⚠️ **A BADGE IS NEVER A PERMISSION.** "Agreed" means a consent record exists
 * — the same fact as `customers.sms_consent` — and says nothing about the
 * platform-scoped `opt_outs` register, quiet hours or the scrubbing registers.
 * The send gate is `permit()`; this is a screen's summary.
 */
final readonly class ConsentBadge
{
    /**
     * @param  list<OutreachChannel>  $agreed  channels with a consent record
     * @param  list<OutreachChannel>  $stopped  channels with a standing suppression
     * @param  list<SuppressionReason>  $stopClasses  why, for wording — never shown as the customer's words unless it was (1225)
     */
    public function __construct(
        public array $agreed,
        public array $stopped,
        public array $stopClasses,
    ) {}

    /**
     * One sentence, worded by who actually said what — decision 1225's rule on
     * a screen: a bounce is not a request, and the owner's own instruction is
     * never presented as the customer having asked.
     */
    public function label(): string
    {
        if ($this->stopped !== []) {
            if (in_array(SuppressionReason::Stop, $this->stopClasses, true)) {
                return 'They asked you to stop';
            }

            if (in_array(SuppressionReason::Complaint, $this->stopClasses, true)) {
                return 'They reported a message as spam';
            }

            if (in_array(SuppressionReason::Bounce, $this->stopClasses, true)) {
                return 'Their contact details aren’t working';
            }

            return 'Never contact — your instruction';
        }

        if ($this->agreed !== []) {
            return 'Agreed to hear from you · '.self::channelList($this->agreed);
        }

        return 'Hasn’t agreed to messages';
    }

    /**
     * The channels in the owner's words.
     *
     * ⚠️ The whole SMS family reads as "text", deliberately: every channel but
     * email rides the phone number (`ConsentService::SMS_FAMILY`), and the
     * architecture lint on owner-facing surfaces is right that this screen must
     * not *offer* a channel by name that nothing can send on — decision 603's
     * remedy, reword rather than widen the allowlist.
     *
     * @param  list<OutreachChannel>  $channels
     */
    private static function channelList(array $channels): string
    {
        $names = array_values(array_unique(array_map(
            fn (OutreachChannel $channel): string => $channel === OutreachChannel::Email ? 'email' : 'text',
            $channels,
        )));

        return implode(' and ', $names);
    }
}
