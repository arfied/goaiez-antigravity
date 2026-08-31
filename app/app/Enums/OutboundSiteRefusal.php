<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\WordPress\WordPressRestClient;
use App\Support\OutboundSiteBudget;

/**
 * Whose brake stopped an outbound request to a customer's own web server —
 * the *reason* half of {@see OutboundSiteBudget::reserve()}.
 *
 * ⛔ **`consume(): bool` COULD ONLY EVER SAY *THAT* WE DID NOT SEND, AND THE
 * SENTENCE AN OWNER READS TURNS ON *WHY*** (9754, 9800–9819).
 * {@see OutboundSiteBudget::reserve()} refuses on two entirely different facts —
 * the host's own `429`/`Retry-After`, and our own per-host cap for the window —
 * and a `false` is the same `false` for both. Everything downstream then had one
 * label, one sentence and one log reason for a two-cause population, so *"Your
 * website asked us to slow down"* was said to an owner whose website had said
 * nothing at all.
 *
 * ⛔ **THE PARTITION IS EXACTLY TWO-WAY HERE AND IT IS THREE-WAY IN
 * {@see FetchRefusalReason}, WHICH IS WHY THIS CARRIES NO
 * `isThisPlatformsOwnDoing()`.** That enum needs a predicate because a caller
 * asking *whose doing was this* has three answers to sort into — ours, theirs,
 * and *nobody's* (`RobotsUnavailable`: we asked and got no usable answer). Every
 * case here is ours or theirs with no third bucket and no unknown, so the
 * question is answered **by the total maps that consume it** —
 * {@see WordPressConnectionRefusal::forOutboundBrake()} and
 * {@see self::vendorLogReason()}, both `match` with no `default`. A boolean
 * beside two total maps would be a third copy of one fact, which is
 * `CLAUDE.md`'s 8460 shape, and its only caller would be a test.
 *
 * ⚠️ **A COOL-DOWN IS *THEIRS* HERE AND IT IS *OURS* IN
 * {@see FetchRefusalReason::CoolingDown}, AND BOTH ARE RIGHT.** That one keys on
 * `source_key`, so the origin that blocked us and the origin now being refused
 * are routinely different hosts; {@see OutboundSiteBudget} keys on the host and
 * stores nothing but what that host's own `Retry-After` asked for, so here the
 * server being refused is the server that asked. **Do not transfer the
 * classification between them without re-reading the key.**
 */
enum OutboundSiteRefusal: string
{
    /**
     * The host's own `429`/`Retry-After` cool-down is still running.
     *
     * ⛔ **THE ONLY CASE HERE ANYTHING MAY DESCRIBE TO A PERSON AS THEIR
     * WEBSITE'S DOING.** {@see WordPressConnectionRefusal::Busy} carries that
     * claim to an owner's screen — *"Your website asked us to slow down"* — and
     * it is true of this case and of nothing else on this enum.
     */
    case HostAskedForRoom = 'host_asked_for_room';

    /**
     * Our own {@see OutboundSiteBudget::REQUESTS_PER_MINUTE} for this host is
     * spent. **Ours**, and it clears inside a minute.
     */
    case MinuteBudgetSpent = 'minute_budget_spent';

    /**
     * Our own {@see OutboundSiteBudget::REQUESTS_PER_DAY} for this host is
     * spent. **Ours**, and it does not clear today.
     *
     * ⚠️ **A SEPARATE CASE FROM {@see self::MinuteBudgetSpent} BECAUSE THE NEXT
     * ACTION IS DIFFERENT, NOT BECAUSE THE ARITHMETIC IS.** Both are our own
     * cap on our own pressure; one is answered by waiting a minute and the other
     * is not answered today at all, and an owner standing in front of a connect
     * form is being told which. Collapsing them would put *"try again in a
     * minute"* in front of somebody for whom it is false for the next twenty-two
     * hours.
     */
    case DayBudgetSpent = 'day_budget_spent';

    /**
     * The label this refusal is recorded under in `vendor_logs`.
     *
     * ⛔ **`VendorLog::failure()` USED ONE STRING FOR ALL THREE, AND ONE OF THE
     * THREE IS NOT A BUDGET AT ALL.** {@see WordPressRestClient::attempt()}
     * wrote `outbound_budget_exhausted` for a host that had asked us for room —
     * so the operator record said *our cap is spent* about a refusal that was
     * the customer's server speaking, which is 6262's argument one layer below
     * the sentence it was made about. It is also the **only** trace either arm
     * leaves: the cache holds the counters and nothing writes a row.
     *
     * ⚠️ **`outbound_budget_exhausted` IS KEPT FOR THE PER-MINUTE ARM RATHER
     * THAN RENAMED WITH ITS SIBLINGS**, {@see FetchRefusalReason}'s rule —
     * *"nothing here is renamed while it is being typed"*. Every existing row
     * and every saved search over one goes on matching, and the string's
     * population narrows rather than moves; decisions 9324 and 9380 both quote
     * it as this client's own brake, and after this it is that and only that.
     */
    public function vendorLogReason(): string
    {
        return match ($this) {
            self::HostAskedForRoom => 'outbound_host_cooldown',
            self::MinuteBudgetSpent => 'outbound_budget_exhausted',
            self::DayBudgetSpent => 'outbound_daily_budget_exhausted',
        };
    }
}
