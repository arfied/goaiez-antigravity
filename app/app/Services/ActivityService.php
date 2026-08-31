<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AutopilotActionType;
use App\Events\ActivityRecorded;
use App\Models\ActivityFeedItem;
use App\Support\Tenancy;

/**
 * The owner's visible history.
 *
 * `29` §2 rule 42: every automated action reaches the feed within 60 seconds.
 * This is the only supported way to write one — the model is append-only, so a
 * mistake here cannot be edited away afterwards, which is the point.
 *
 * Titles come from AutopilotActionType rather than the caller, so the vocabulary
 * stays closed and jargon cannot leak in one call site at a time.
 *
 * Pairs with AuditService, and the distinction is worth holding: this is what
 * the owner sees, in their language; the audit log is what compliance reads, in
 * full detail, and the two have different audiences and different retention.
 *
 * ## The title is the field that is printed, so the rule lives here
 *
 * ⛔ **THE PROTECTION USED TO BE UPSIDE DOWN, AND THIS DOCBLOCK WAS THE
 * EXPOSURE** (6293, closed at 6642). `metadata` carries a written rule against
 * customer content and `Livewire\Account\Activity` renders **none** of it;
 * `title` carried no rule at all and that screen prints it **verbatim**.
 * The comment that used to sit on the `title` assignment offered
 * *"Replied to Sam's 4-star review"* as the example of a good override — an
 * invitation, in the one file every writer reads, to put a member of the
 * public's name on a screen and into an append-only table.
 *
 * ✅ **NO WRITER IN `app/` EVER DID IT** — every override was and is a provider
 * name, a platform name, a campaign state, a credit product, a ticket reference
 * or a currency amount. That is what made this cheap to fix and easy to leave.
 *
 * ## An allowlist, never a scrubber
 *
 * ⛔ **A CONTENT FILTER OVER TITLES WAS CONSIDERED AND REFUSED IN WRITING**
 * (6643). This codebase has ruled twice, in `Support\VendorLog` and in
 * `Exceptions\WordPressRequestFailed`, that a record is built from an
 * **allowlist and never by redacting something you were handed**: *"a denylist
 * is the wrong shape for this problem — it fails open the first time a provider
 * adds a field, and the failure is invisible because the log looks fine right up
 * until someone reads it."* A regex hunting email addresses and phone numbers in
 * a title is that denylist, and it fails open on **the exact example the old
 * comment gave**, because a first name is not detectable. Worse, it would read
 * as protection and stop the next reviewer looking — 314–316's shape, which
 * `CLAUDE.md` records as having bitten four times.
 *
 * So the rule is an allowlist in two halves, and **neither half inspects a
 * single character of the string**:
 *
 * 1. **Runtime** — {@see self::MAY_CARRY_ITS_OWN_TITLE}. Only an action type
 *    that has an argued reason to say something more specific than its own
 *    vocabulary entry may carry an override at all. Every other action falls
 *    back to {@see AutopilotActionType::title()}.
 * 2. **Build** — `tests/Feature/Architecture/ActivityTest.php` enumerates every
 *    file in `app/` that supplies a title, with the provenance of the words
 *    written beside it, and fails the build on one that is not on the list. It
 *    also asserts that the set of action types those call sites use is
 *    **equal** to the list below, so a permitted case with no writer and a
 *    writer with no permission both redden.
 *
 * ⛔ **WHAT THIS DOES NOT DO, SAID PLAINLY** (352, 397, 565): it does not stop
 * one of the four permitted writers interpolating a customer's name. Nothing
 * mechanical can. What stops that is a reviewer reading a lint failure that
 * names the file, which is the same protection `metadata` has had all along —
 * the difference is that `title` now has it too.
 */
final class ActivityService
{
    /**
     * The action types permitted to carry a title of their own.
     *
     * ⛔ **AN ALLOWLIST, AND IT IS THE HALF OF 6293's FIX THAT RUNS IN
     * PRODUCTION.** Thirty-one callers reach {@see self::record()} and any of
     * them could pass any string; after this, all but these four are answered
     * with the closed vocabulary whatever they pass.
     *
     * ⚠️ **EACH ONE IS HERE FOR A REASON THAT IS WRITTEN DOWN, AND THE
     * ARGUMENT IS ALWAYS THE SAME ONE**: the case names *what happened* and the
     * override names *which of several things it was*, which is the one thing a
     * closed vocabulary cannot express.
     *
     *   OwnerActionNeeded       thirteen different things share one title, so
     *                           `TokenService` names the provider to reconnect
     *                           and `RunCampaignJob` names the campaign state
     *   ReviewDestinationOpened `17` FPR-04b fixes the wording per destination —
     *                           "opened Google", never "left a review"
     *   SupportMadeAChange      `28` §9.4's sentence takes the thing changed and
     *                           the ticket reference from the caller
     *   SystemMessage           a billing message with no case of its own
     *
     * ⚠️ **`OwnerAttention` IS THE READER SIDE OF THIS EXACT LIST** and keys on
     * `title !== action->title()` to decide whether a writer said something
     * specific. An action removed from here starts reading as the shared label
     * on that screen, which is visible rather than silent — and the lint fails
     * first anyway.
     *
     * @var list<AutopilotActionType>
     */
    private const array MAY_CARRY_ITS_OWN_TITLE = [
        AutopilotActionType::OwnerActionNeeded,
        AutopilotActionType::ReviewDestinationOpened,
        AutopilotActionType::SupportMadeAChange,
        AutopilotActionType::SystemMessage,
    ];

    /**
     * `activity_feed.title` is a `string` column, so 255 is the width Postgres
     * will take.
     *
     * ⚠️ **REFUSED RATHER THAN TRUNCATED, AND REFUSED RATHER THAN THROWN.**
     * Truncating produces a sentence that stops mid-word on the one screen `22`
     * governs; letting it through throws a `22001` from inside whatever business
     * action was being recorded, which is the hard-fail rule 43's surviving half
     * forbids (3294). The row is still written, under the vocabulary's own
     * title, and `OwnerAttention` upgrades it from the bag where it can.
     */
    private const int TITLE_LIMIT = 255;

    /**
     * Record something that happened, and push it to any open staff screen.
     *
     * @param  array<string, mixed>  $metadata  Never customer content — see the
     *                                          broadcast payload rules.
     * @param  string|null  $title  ⛔ **PRINTED VERBATIM ON THE OWNER'S FEED,
     *                              AND KEPT FOR EVER — SO IT NAMES A THING THIS
     *                              SYSTEM DECIDED, NEVER A PERSON IT DECIDED
     *                              ABOUT.** A provider, a platform, a campaign
     *                              state, a credit product, a ticket reference,
     *                              an amount this system counted. Not a
     *                              customer's name, their words, their address,
     *                              their number or their review. Honoured only
     *                              for {@see self::MAY_CARRY_ITS_OWN_TITLE};
     *                              anything else falls back to the closed
     *                              vocabulary, and adding a call site fails the
     *                              build until it is argued in `ActivityTest`.
     */
    public function record(
        AutopilotActionType $action,
        ?int $locationId = null,
        array $metadata = [],
        ?string $title = null,
    ): ActivityFeedItem {
        $item = ActivityFeedItem::create([
            'location_id' => $locationId,
            'action_type' => $action->value,
            'title' => self::titleFor($action, $title),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);

        ActivityRecorded::dispatch(
            Tenancy::idOrFail(),
            (int) $item->id,
            $action,
        );

        return $item;
    }

    /**
     * The words that go on the screen: the caller's, if it is allowed any.
     *
     * ⚠️ **A DROPPED OVERRIDE IS SILENT AT RUNTIME AND LOUD AT BUILD TIME, AND
     * THAT IS THE DESIGN RATHER THAN AN OVERSIGHT.** The lint enumerates every
     * call site in `app/`, so the only ways to reach the fallback arm are a
     * test, or a call that reaches the enum through a variable — the one shape
     * the lint says it cannot see. Logging the refused string would put the very
     * words this method exists to keep off a screen into a log file instead.
     *
     * ⚠️ **AN EMPTY OR BLANK TITLE IS AN OVERRIDE THAT PRODUCED NOTHING**, and
     * `activity_feed.title` is `NOT NULL` because *"the title is what the feed
     * is"*. A blank heading is a row an owner cannot read at all, so it takes
     * the vocabulary's answer too.
     */
    private static function titleFor(AutopilotActionType $action, ?string $title): string
    {
        if ($title === null || ! in_array($action, self::MAY_CARRY_ITS_OWN_TITLE, true)) {
            return $action->title();
        }

        $trimmed = trim($title);

        if ($trimmed === '' || mb_strlen($trimmed) > self::TITLE_LIMIT) {
            return $action->title();
        }

        return $trimmed;
    }
}
