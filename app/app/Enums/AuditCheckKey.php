<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four checks `29` §6.2 specifies, and the order the visitor sees them in.
 *
 * An enum rather than a string on each class because the key does three jobs
 * that have to agree: it groups findings inside `public_audits.findings`, it
 * names the check in the "we could not check this" note a visitor reads, and it
 * is the stable identifier slice H copies into `wizard_progress.data`. A typo in
 * any one of those is silent.
 *
 * THE ORDER IS A COST DECISION AS MUCH AS A UX ONE. The two Places-backed checks
 * run first because their data is already paid for by the time they run — Place
 * Details is one call shared between them (AuditContext). The two fetch-backed
 * checks run last because they depend on a stranger's web server answering,
 * which is the slowest and least reliable part of the audit and the part most
 * likely to eat the <20s budget. Findings persist as each check completes, so a
 * slow site delays the last two cards rather than the whole page.
 */
enum AuditCheckKey: string
{
    /** Hours, categories, photos, description — from Place Details. */
    case GbpCompleteness = 'gbp_completeness';

    /** Rating, review count, reply-rate, and the unnamed nearby aggregate. */
    case ReviewStats = 'review_stats';

    /** Phone and address on the website versus the Google listing. */
    case NapQuickScan = 'nap_quick_scan';

    /** Reachable, HTTPS, title, schema, viewport, timing. */
    case SiteBasics = 'site_basics';

    /**
     * Every check, in the order the engine runs them.
     *
     * @return list<self>
     */
    public static function inRunOrder(): array
    {
        return [
            self::GbpCompleteness,
            self::ReviewStats,
            self::NapQuickScan,
            self::SiteBasics,
        ];
    }

    /**
     * What the visitor sees this check called.
     *
     * Outcome language (`22`): what was examined, never how. "Google listing"
     * rather than "GBP completeness via Places Details".
     */
    public function label(): string
    {
        return match ($this) {
            self::GbpCompleteness => 'Google listing',
            self::ReviewStats => 'Reviews and replies',
            self::NapQuickScan => 'Contact details match',
            self::SiteBasics => 'Website basics',
        };
    }

    /**
     * Whether this check needs an outbound page fetch, and therefore the gateway.
     */
    public function needsSiteFetch(): bool
    {
        return $this === self::NapQuickScan || $this === self::SiteBasics;
    }
}
