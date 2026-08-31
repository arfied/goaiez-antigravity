<?php

declare(strict_types=1);

namespace App\Support\Account;

use App\Services\Crm\CrmTasks;
use App\Services\Reviews\ReviewReplies;
use App\Services\Reviews\ReviewRouter;
use App\Support\Tenancy;

/**
 * The numbers beside the owner's navigation items — resolved at render, never
 * declared.
 *
 * ⚠️ **THIS CLASS EXISTS SO `OwnerNav` NEVER GROWS A QUERY.** The nav is a
 * static list on purpose (decision 1446): the shell renders the on-hold status
 * page, which is what a tenant in trouble is shown, and a nav that needed a
 * tenant would fail on the one screen that exists for the account whose tenancy
 * is the problem. So an item declares a badge *key*, and this resolves the
 * count where the nav partial is rendered — behind a `Tenancy::id()` guard, so
 * a page rendered with no tenant (the on-hold page renders with `:nav="false"`
 * and never reaches here; a support agent with no business would) simply shows
 * no badges rather than throwing.
 *
 * ⚠️ **A ZERO IS ABSENT, NOT RENDERED.** The badge is `44` §2's "don't let me
 * forget" mechanism; a permanent grey `0` beside More would train the eye to
 * ignore the one number whose whole job is to interrupt.
 */
final class OwnerNavBadges
{
    /**
     * Open follow-ups due today or overdue — `44` §2's "due-today count badges
     * the More tab".
     */
    public const string FOLLOW_UPS_DUE = 'crm.follow_ups_due';

    /**
     * Suggested Google-reply drafts waiting on the owner (GBP-05).
     */
    public const string REPLY_DRAFTS = 'reviews.reply_drafts';

    /**
     * Recovery conversations still waiting on somebody (2689).
     *
     * Counted through `ReviewRouter`, never `TriageConversation` — the
     * chokepoint lint holds that model to one service and decision 906 refused
     * to widen it for exactly this shape of reader.
     */
    public const string TRIAGE_OPEN = 'reviews.triage_open';

    /**
     * Every badge worth showing, keyed by the item's badge key.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        if (Tenancy::id() === null) {
            return [];
        }

        $counts = [];

        foreach (OwnerNav::all() as $item) {
            if ($item->badge === null) {
                continue;
            }

            try {
                $count = match ($item->badge) {
                    self::FOLLOW_UPS_DUE => app(CrmTasks::class)->dueTodayCount(),
                    self::REPLY_DRAFTS => app(ReviewReplies::class)->pendingCount(),
                    self::TRIAGE_OPEN => app(ReviewRouter::class)->openTriageCount(),
                    default => 0,
                };
            } catch (\Throwable) {
                $count = 0;
            }

            if ($count > 0) {
                $counts[$item->badge] = $count;
            }
        }

        return $counts;
    }

    /**
     * What a screen reader hears after the number.
     *
     * ⚠️ **THIS EXISTS BECAUSE THE SUFFIX WAS HARDCODED TO `" due today"` IN THE
     * NAV PARTIAL AND WAS ALREADY WRONG FOR ONE OF THE TWO BADGES** (2709). A
     * sighted owner saw `2` beside *Reply drafts*; a screen-reader user heard
     * *"2 due today"* — and a reply draft has no due date, nothing makes one
     * overdue, and no part of this product has ever claimed otherwise. It was
     * `44` §2's sentence about follow-ups, copied onto the markup that renders
     * every badge. Adding a third badge would have made it wrong twice, so the
     * words move next to the key that decides the number.
     *
     * ⚠️ **AND IT IS NOT DERIVED FROM THE LABEL.** *"Win back customers, 3 win
     * back customers"* is what a generated suffix produces; these are written
     * to be heard after a number, which is a different sentence from the one
     * written to be read beside an icon.
     */
    public static function screenReaderSuffix(string $badge): string
    {
        return match ($badge) {
            // `44` §2's own words, and the only badge they were ever true of.
            self::FOLLOW_UPS_DUE => 'due today',
            self::REPLY_DRAFTS, self::TRIAGE_OPEN => 'waiting for you',
            default => 'waiting for you',
        };
    }

    /**
     * What a screen reader hears after the summed number on the More tab.
     *
     * One phrase rather than a list, because the sum spans kinds — a follow-up
     * that is due, a draft that is not, and a customer nobody has rung back.
     * The only honest thing true of all of them is that they are waiting.
     */
    public static function summarySuffix(): string
    {
        return 'waiting for you';
    }
}
