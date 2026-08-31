<?php

declare(strict_types=1);

namespace App\Support\Campaigns;

use App\Models\CampaignPack;
use App\Services\Campaigns\CampaignPacks;

/**
 * THE TWELVE CAMPAIGN PACKS — T296 §B3, loaded into `campaign_packs` by
 * `php artisan packs:sync` (CC-5 §1).
 *
 * `PlanOfferCatalog` and `LegalDraftManifest`'s sibling: one reviewed
 * declaration of authored content in `app/Support`, one idempotent command, and
 * rows underneath that a screen reads. T296 §B1's mirror law is why the rows
 * exist at all — *"a gallery card is a VIEW of its SEQ pack … a string born in
 * the gallery is a bug."*
 *
 * ## ⛔ THE MESSAGE BODIES ARE NOT HERE, AND THAT IS A REFUSAL RATHER THAN AN
 * UNFINISHED FILE
 *
 * Every name and one-liner below is T296 §B3 **verbatim**, and §B3 is all this
 * repository has: it is headed *"owner-language renders of the SEQ packs of
 * record"*, and the SEQ packs of record are in the GOAIEZ package the owner
 * holds. The whole of `docs/incoming-2026-08-18/` was searched; no pack's
 * message bodies ship anywhere in it.
 *
 * Writing them here would mean **inventing marketing SMS copy that leaves over
 * the GOAIEZ 10DLC brand, from our own number pool, to a list whose only sending
 * basis may be a tenant's attestation** (decisions 2098–2102).
 * `ReactComposer`'s own docblock refuses exactly that, in exactly those terms:
 * *"which is the one category of guess this codebase has a rule against"*. So
 * `messages` is `[]` on all twelve, {@see CampaignPack::isAuthored()} answers
 * false, and {@see CampaignPacks::activate()} refuses by name. Decision 5253;
 * what is owed is twelve packs' copy from the owner and nothing else.
 *
 * ⚠️ **T296 §B2's "WHO IT'S FOR" CHIP IS NOT A COLUMN, FOR THE SAME REASON.**
 * The card grammar asks for one and §B3 does not author twelve of them. Writing
 * them would be this file inventing product copy in a slice whose whole argument
 * is that it does not, and a column seeded empty for ever is `CLAUDE.md`'s first
 * recurring failure. It arrives with the message bodies or not at all.
 *
 * ⚠️ **AN EMPTY PACK IS NOT AN EMPTY ROW.** The name and the one-liner are the
 * authored half of every pack and they are stored, so a row that cannot be turned
 * on is still a row somebody wrote, and the activation path is proved end to end
 * in `tests/Feature/Campaigns/CampaignPackTest.php` against a pack that does
 * carry messages.
 *
 * ⛔ **AND *"WHAT IS MISSING IS THE COPY, NOT THE MECHANISM"* WAS FALSE, AND SO
 * WAS THE SENTENCE THAT CARRIED IT — CORRECTED 2026-08-28 (11700).** This
 * paragraph read *"The gallery renders all twelve today — the names and the
 * promises are the authored half and are what a tenant reads"*. **There is no
 * gallery.** {@see CampaignPacks::gallery()} and {@see CampaignPacks::activate()}
 * have no caller outside `tests/`, and no view under `resources/` renders a pack
 * — so no tenant has ever read one of these names. ⛔ **There are TWO absences
 * stacked and fixing either alone changes nothing a tenant can see** (11524): the
 * copy, which is the owner's, and the entry point, which is a wave of its own.
 * 11520–11524 carry the measurement and the escape hatches that were checked.
 * ⚠️ **The superseded sentence is kept because it is the evidence**: a file
 * arguing that only the copy is missing is what a later reader takes as proof
 * that the mechanism is wired.
 */
final class CampaignPackCatalog
{
    /**
     * Every pack this application ships with, in gallery order.
     *
     * ⚠️ **THE ORDER IS THE OWNER'S NUMBERING AND `position` CARRIES IT**, not
     * the array index and not the id: T296 §B3 numbers these 1–12 and a
     * thirteenth arriving in the middle must not renumber the rest by accident.
     *
     * @return list<array{key: string, name: string, one_liner: string, messages: list<array{day_offset: int, body: string}>, position: int}>
     */
    public static function packs(): array
    {
        return [
            self::pack(1, 'welcome_new_customer', 'Welcome a new customer',
                'First impressions, on autopilot.'),

            self::pack(2, 'review_follow_up', 'The review follow-up',
                'Happy customers, asked at the right moment.'),

            self::pack(3, 'win_back_the_quiet_ones', 'Win back the quiet ones',
                "Customers you haven't seen in a while hear from you first."),

            self::pack(4, 'no_show_guard', 'The no-show guard',
                'Reminders that actually get answered.'),

            self::pack(5, 'seasonal_push', 'The seasonal push',
                'Your busy-season note, sent before the season.'),

            self::pack(6, 'referral_ask', 'The referral ask',
                'Happy customers know people. Ask.'),

            self::pack(7, 'rebooking_cycle', 'The rebooking cycle',
                'Regulars stay regular.'),

            self::pack(8, 'estimate_follow_up', 'The estimate follow-up',
                "Quotes that don't die in a drawer."),

            self::pack(9, 'new_lead_nurture', 'New-lead nurture',
                'Leads warm up instead of going cold.'),

            self::pack(10, 'birthday_note', 'The birthday note',
                'Small gesture, remembered business.'),

            self::pack(11, 'post_job_care', 'Post-job care',
                'The check-in that catches problems early.'),

            self::pack(12, 'openings_and_waitlist', 'Openings & waitlist',
                'An empty slot texts the right person.'),
        ];
    }

    /**
     * One pack's declaration.
     *
     * ⚠️ **`messages` IS ALWAYS `[]` AND THE PARAMETER IS DELIBERATELY ABSENT.**
     * A defaulted argument would read as *"nobody has filled this one in yet"*,
     * which is the reading the class docblock exists to prevent: none of the
     * twelve has authored copy, the reason is written down, and the day it
     * arrives is the day this signature grows a parameter and every caller has
     * to supply it.
     *
     * @return array{key: string, name: string, one_liner: string, messages: list<array{day_offset: int, body: string}>, position: int}
     */
    private static function pack(int $position, string $key, string $name, string $oneLiner): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'one_liner' => $oneLiner,
            'messages' => [],
            'position' => $position,
        ];
    }
}
