<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Content\AuthorByline;
use App\Services\Content\BylineCheck;
use App\Services\Fetch\DirectFetchGateway;
use App\Services\Fetch\FetchResult;

/**
 * Why {@see DirectFetchGateway} declined to open a socket — the *reason* half of
 * {@see FetchOutcome::Refused}.
 *
 * ⛔ **A TYPE RATHER THAN THE SEVEN BARE STRINGS THIS USED TO BE, BECAUSE ONE OF
 * THEM WAS ALREADY HAND-COPIED INTO A SECOND FILE** (9480–9499).
 * `AuthorByline::ROBOTS_REFUSAL` was a re-typed `'robots_disallow'` held against
 * the gateway by a lint reading raw source, which is `CLAUDE.md`'s 8460 shape
 * *"a lint holding its own copy of the pattern its guard reads … EVEN WHEN BOTH
 * COPIES ARE CORRECT TODAY"*. The copy is gone and the lint with it; what holds
 * the two files together now is that they name the same case.
 *
 * ⚠️ **THE VALUES ARE THE STRINGS THAT WERE ALREADY THERE.** They reach logs
 * (`SiteProbe` writes `refused:<value>`) and a saved search over an old log line
 * must go on matching, so nothing here is renamed while it is being typed.
 */
enum FetchRefusalReason: string
{
    /** The source's kill switch is on. Ours, and deliberate. */
    case KillSwitch = 'kill_switch';

    /**
     * A `guided_only` source — never fetchable at any tier, permanently
     * (`40` §6.2).
     */
    case GuidedOnly = 'guided_only';

    /** The requested tier is above what this source's ceiling permits. */
    case AboveCeiling = 'above_ceiling';

    /** The source is inside a cool-down from an earlier block. */
    case CoolingDown = 'cooling_down';

    /** Our own politeness budget for this source is spent. */
    case RateBudget = 'rate_budget';

    /**
     * The origin's own `robots.txt` was read and refuses this path.
     *
     * ⛔ **THIS IS THE ONLY REASON ANYTHING MAY DESCRIBE TO A PERSON AS *"YOUR
     * WEBSITE IS TELLING US NOT TO"***. {@see BylineCheck::$ownRobotsRefused}
     * carries that claim to an owner's screen, naming a file on their server and
     * asking whoever looks after their site to change it — see
     * {@see AuthorByline::unreachableAboutPageSentence()} for the sentence.
     */
    case RobotsDisallow = 'robots_disallow';

    /**
     * The origin's `robots.txt` could not be obtained or parsed, so no rule of
     * theirs was read at all — added 9480–9499, and it is what half of
     * {@see self::RobotsDisallow}'s population had been.
     *
     * ⛔ **THE GATEWAY STILL REFUSES**, which is `RobotsPolicy`'s fail-closed
     * promise and is untouched. What changes is that nothing may now call this
     * the origin's doing.
     */
    case RobotsUnavailable = 'robots_unavailable';

    /** the address is inside this server's own network, so it is never fetched */
    case PrivateAddress = 'private_address';

    /**
     * Whether waiting is the remedy.
     *
     * ⚠️ **{@see FetchOutcome::Refused} SAYS *"NEVER COOLS DOWN — WAITING CHANGES
     * NOTHING ABOUT A RULE"*, AND THAT IS TRUE OF A RULE AND FALSE OF AN
     * OUTAGE.** Nothing consumes this yet and it is not a cool-down trigger — it
     * is here so that the distinction the enum draws is stated once, in the
     * type, rather than re-derived by whichever caller notices next.
     */
    public function clearsItself(): bool
    {
        return match ($this) {
            self::CoolingDown, self::RateBudget, self::RobotsUnavailable => true,
            self::KillSwitch, self::GuidedOnly, self::AboveCeiling, self::RobotsDisallow, self::PrivateAddress => false,
        };
    }

    /**
     * Whether this refusal is a rule the fetched origin itself published.
     *
     * ⛔ **THE ONE QUESTION A CALLER SHOULD ASK RATHER THAN COMPARING CASES**
     * ({@see FetchResult::$refusalReason}). Every other reason on this enum is
     * ours — a budget, a switch, a ceiling, a cool-down, or an outage — and
     * telling somebody their own site turned us away on the strength of one of
     * those sends them to edit a file that was never the problem.
     *
     * ⚠️ **ITS PARTITION IS TWO-WAY AND THE SET IT ANSWERS ABOUT IS THREE-WAY —
     * SEE {@see self::isThisPlatformsOwnDoing()}.** Two-way is right for
     * {@see AuthorByline::check()}, which decides one yes/no about one sentence;
     * a caller deciding *whose doing was this* needs both questions, because a
     * `false` here covers our own kill switch **and** an outage at their host,
     * and those are not the same fact.
     */
    public function namesTheOriginsOwnRule(): bool
    {
        return $this === self::RobotsDisallow;
    }

    /**
     * Whether this platform decided the refusal out of its own configuration and
     * its own ledger, with nothing about the origin consulted.
     *
     * ⛔ **THE COMPLEMENT OF {@see self::namesTheOriginsOwnRule()} IS NOT THIS
     * QUESTION, WHICH IS THE WHOLE REASON THIS EXISTS** (9740–9759). Between
     * them the enum partitions three ways rather than two:
     *
     *   ours      a kill switch, a `guided_only` ceiling, a tier above the
     *             ceiling, a cool-down, a spent rate budget — five brakes of
     *             ours, every one applied before any request to the origin
     *   theirs    {@see self::RobotsDisallow}, and only that
     *   nobody's  {@see self::RobotsUnavailable} — we asked and got no usable
     *             answer, which is neither their rule nor our policy
     *
     * ⛔ **A CALLER THAT RENDERS A REFUSAL TO A PERSON OWES BOTH QUESTIONS.**
     * Asking only the second turns *"our own four-a-minute budget is spent"*
     * into a sentence about somebody else's website — which is what
     * {@see IndexingRefusal::RobotsUnreadable} said for all five of the first
     * group until 9740–9759, sending an operator to go and look at a
     * `robots.txt` that was fine.
     *
     * ⚠️ **THE TWO PARTITIONS MAY NOT OVERLAP AND A TEST SAYS SO** —
     * `tests/Feature/Indexing/SitemapRefusalTest.php`'s *"the three answers are
     * three, and no refusal reason claims to be two of them"*, which also holds
     * an anti-vacuity floor under all three buckets. Both methods are a `match`
     * with no default, so an eighth case is a fatal rather than a wrong answer;
     * what the test adds is that a case cannot be sorted by call order instead
     * of by its meaning.
     *
     * ⚠️ **A COOL-DOWN IS OURS EVEN THOUGH AN ORIGIN'S 403 IS WHAT STARTS ONE.**
     * `DirectFetchGateway::coolingDown()` keys on `source_key` and nothing else,
     * so the origin that blocked us and the origin now being refused are
     * routinely different hosts — which makes attributing it to the second one
     * false twice over.
     */
    public function isThisPlatformsOwnDoing(): bool
    {
        return match ($this) {
            self::KillSwitch, self::GuidedOnly, self::AboveCeiling,
            self::CoolingDown, self::RateBudget, self::PrivateAddress => true,
            self::RobotsDisallow, self::RobotsUnavailable => false,
        };
    }
}
