<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a campaign decided who it is for (T137 `SL-2`, the `L3` lane cut's
 * *"campaign engine … manual-select mode"*).
 *
 * ⚠️ **TWO MODES, AND THE SECOND IS NOT A CONVENIENCE.** `S4`'s dormancy rule
 * cannot answer for a list the tenant uploaded yesterday: `CustomerImports`
 * writes no activity date and the file has no column for one, so every imported
 * contact's newest inbound signal is *nothing*, measured from the day they
 * entered the book. That makes them **not** dormant on day one, which is the
 * safe answer and is also useless to the one tenant who most wants to reach
 * them. `ManualSelect` is the honest path for that case: a person chooses the
 * recipients, and every other rail — the recorded sending basis, the arbiter,
 * recipient-local quiet hours, the credit debit, the confirmation — applies
 * exactly as it does to the automatic one.
 */
enum CampaignAudience: string
{
    /**
     * `S4` — dormant by the inbound-only clock.
     *
     * ⚠️ **OUR OWN OUTBOUND SENDS NEVER RESET IT.** That is the whole point of
     * the rule as staged, and the bug it exists to prevent is self-defeating:
     * a campaign that reset the clock would keep re-qualifying the people it
     * had just texted, forever.
     */
    case Dormant = 'dormant';

    /**
     * Somebody chose these contacts by hand.
     *
     * ⛔ **A CHOICE IS NOT A BASIS.** Selecting a contact does not make them
     * sendable: `ConsentService::permit()` is still asked for every one, and
     * decision 2099's replacement rule stands — no send without *a* recorded
     * basis, platform-captured consent or a named tenant attestation, with
     * STOP, HELP and suppression unconditional under both.
     */
    case ManualSelect = 'manual_select';
}
