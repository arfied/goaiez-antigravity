<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Enums\AutopilotActionType;
use App\Enums\ContentAuditFinding;
use App\Models\ActivityFeedItem;
use App\Services\Content\Publishing;
use App\Services\Content\PublishingVolume;

/**
 * What *"Something needs your attention"* actually means, one row at a time.
 *
 * ⛔ **THIS IS THE FINDING 6073 CALLED LARGER THAN ITS OWN SLICE, ANSWERED.**
 * Every `OwnerActionNeeded` on this platform — a missing About page, a spent
 * publishing cap, a self-audit pause, a change we could not take back off a
 * website, a review nobody could moderate, a number we stopped texting from —
 * is filed under **one shared title**, with its specifics in a `metadata` bag
 * nothing read. A feed that printed that title a hundred times would be the
 * defect with a URL, so the screen prints **this** instead, and falls back to
 * the shared title only for a bag it does not recognise.
 *
 * ## Reader-side, entirely
 *
 * ⛔ **NOT ONE WRITER IS TOUCHED, AND THAT IS A DESIGN POSITION RATHER THAN A
 * SCOPE LIMIT** (6281). The obvious fix is to give each writer a title, which
 * is thirteen edits across five owners' files, is impossible to keep complete,
 * and puts owner-facing prose at thirteen addresses where `22`'s vocabulary
 * rules have to be re-argued each time. `AutopilotActionType::title()` already
 * establishes the opposite: *"titles live here rather than at each call site so
 * the vocabulary stays closed and a single test can assert all of it"*. This is
 * that rule extended to the one case the closed vocabulary cannot express,
 * because the case is not "what happened" but "which of thirteen things
 * happened".
 *
 * ## What decides the sentence
 *
 * ⛔ **THE BAG, NEVER THE AUTOMATION NAME** — 6062's rule, taken whole.
 * `metadata['automation']` is a string that lives in whichever file files the
 * row, and matching on it is 5817's rename hazard: a sentence that silently
 * stops appearing, for ever, with the build green. Three separate automations
 * legitimately file *"we could not take our change back off"*
 * (`UndoSiteChangeJob`, `ChangeMeasurer`, `SpeedDecider`) and they mean the
 * same thing, so the key that means it — `site_change_id` — is what is matched.
 *
 * ⚠️ **A WRITER THAT SUPPLIED ITS OWN TITLE IS LEFT ALONE.** `TokenService`
 * names the provider to reconnect and `RunCampaignJob` names the campaign
 * state; those rows already say something specific, and a second sentence
 * derived here would be this file overruling a writer that did the right thing.
 * The tell is `title !== action_type->title()`, which is exact and needs no
 * list.
 *
 * ⚠️ **AND THAT TELL IS ONLY HONEST BECAUSE THE TITLE NOW HAS A RULE OF ITS
 * OWN** (6293, 6642). It used to have none at all while `metadata` had one —
 * the protection was upside down, on the field this screen prints verbatim.
 * `ActivityService::MAY_CARRY_ITS_OWN_TITLE` is the reader side of this
 * paragraph made executable: four action types may override, everything else is
 * answered with the closed vocabulary, and every call site that does override is
 * enumerated with the provenance of its words in `Architecture/ActivityTest`.
 *
 * ## What may be printed, and what may not
 *
 * ⛔ **`metadata` IS NEVER RENDERED, IN WHOLE OR IN PART.** It is a free-form
 * `jsonb` bag written by thirty-one callers under a rule
 * (`AutopilotJob::activityMetadata()`) that says *never customer content* — a
 * rule this file cannot verify and must not depend on. So nothing here echoes a
 * value: every sentence is a **constant in this file**, chosen by the bag.
 * Two exceptions, both counts of the tenant's own pages
 * (`published`/`allowed`), cast to `int` before they reach a string.
 *
 * ⛔ **`metadata['url']` AND `metadata['detail']` ARE READ BY NOTHING HERE, ON
 * PURPOSE.** `ChangeMeasurer` files the full URL of the page it edited and
 * 5970 already ruled that a full URL with a query string is where a booking
 * reference ends up; `detail` is free text handed back by somebody else's
 * website — a plugin error, a WAF page, a 500 body — and `MessageLog::explain()`
 * settled the identical question for vendor strings: *"the raw text stays in
 * the row for whoever debugs it"*. `CLAUDE.md`'s second tie-breaker is less
 * exposed PII and both of these are on the wrong side of it.
 */
final class OwnerAttention
{
    /**
     * The sentence the feed prints for one row.
     *
     * Falls back to the row's own title, which is either the writer's or the
     * closed vocabulary's — so an unrecognised bag reads as the gap it is
     * rather than as a sentence nobody checked.
     */
    public static function headlineFor(ActivityFeedItem $item): string
    {
        return self::specificsFor($item) ?? $item->title;
    }

    /**
     * What this particular `OwnerActionNeeded` is about, or null.
     *
     * ⚠️ **ORDER IS PART OF THE ANSWER.** `reason` is the most specific key and
     * is asked first; `site_change_id` is asked before `growth_page_id` because
     * a change set is a page we already put something on and a growth page is
     * one we have not. Nothing in the schema stops a future writer supplying
     * two of these, and the first match is the one that wins deliberately
     * rather than by accident of ordering.
     */
    public static function specificsFor(ActivityFeedItem $item): ?string
    {
        if ($item->action_type !== AutopilotActionType::OwnerActionNeeded) {
            return null;
        }

        // The writer said something specific already. Leave it alone.
        if ($item->title !== AutopilotActionType::OwnerActionNeeded->title()) {
            return null;
        }

        $bag = $item->metadata ?? [];

        return match (true) {
            self::text($bag, 'reason') === 'triage_opened' => 'A customer left you private feedback that nobody has answered yet.',
            self::text($bag, 'reason') === 'reply_posting_unavailable' => 'We wrote a reply to one of your reviews and could not post it. It is saved for you.',
            // ⛔ ITS OWN SENTENCE BECAUSE THE OUTCOME IS UNKNOWN, NOT FAILED
            // (6824). The line above says a thing did not happen; here nobody
            // knows whether it did — the request left us and no answer came
            // back. Sharing that arm would tell an owner their reply is not on
            // Google when it may be, and the owner is the only party who can go
            // and look, so the sentence has to send them to look.
            self::text($bag, 'reason') === 'reply_publish_unconfirmed' => 'We sent a reply to Google and did not get an answer back. Check that review on Google — if the reply is not there, approve it again.',
            self::text($bag, 'needs') === 'about_page' => self::aboutPage($bag),
            array_key_exists('cap', $bag) => self::capSpent($bag),
            array_key_exists('findings', $bag) => self::selfAudit($bag),
            array_key_exists('site_change_id', $bag) => 'We could not take one of our changes back off your website, so it is still there.',
            array_key_exists('growth_page_id', $bag) => self::growthPage($bag),
            array_key_exists('number_id', $bag) => 'We stopped sending texts from one of your numbers while we look into it.',
            array_key_exists('review_id', $bag) => 'One of your reviews is waiting to be checked before it can show on your website.',
            default => null,
        };
    }

    /**
     * The two ways a page we wrote ends up waiting on the owner (6292, 6641).
     *
     * ⛔ **TWO WRITERS FILED ONE BAG AND THE FEED COULD NOT TELL AN OWNER WHICH
     * HAD HAPPENED.** `Publishing::stop()` — they replied STOP, so the page is
     * held and **nothing republishes it on a clock** (5565) — and
     * `Publishing::handOver()` — the T4 rung, where the copy was emailed for
     * them to paste — both filed `['automation' =>
     * 'content.publish_growth_page', 'growth_page_id' => …]`. 6293's slice
     * shipped the sentence true of both rather than guessing, and this is the
     * discriminator it said was ready for the day one existed.
     *
     * ⚠️ **THE TWO SENTENCES ASK FOR DIFFERENT THINGS, WHICH IS THE WHOLE
     * REASON THIS IS WORTH A KEY.** One is *decide what to do with it*; the
     * other is *it is in your email, paste it*. An owner given the wrong one
     * goes looking in the wrong place.
     *
     * ⚠️ **AN UNRECOGNISED VALUE, OR NONE, KEEPS THE SENTENCE TRUE OF BOTH.**
     * Rows filed before this key existed carry no `waiting_because` at all, and
     * `activity_feed` is append-only — so the fallback is not a defensive
     * flourish, it is what every historical row renders as.
     *
     * @param  array<string, mixed>  $bag
     */
    private static function growthPage(array $bag): string
    {
        return match (self::text($bag, Publishing::WAITING_KEY)) {
            Publishing::WAITING_OWNER_STOPPED => 'You told us to stop, so a page we wrote for you is being held. '
                .'Nothing has gone onto your website, and it will not go up on its own.',
            Publishing::WAITING_HANDED_TO_OWNER => 'We have emailed you a page we wrote for you to put on your website. '
                .'Nothing has gone onto your website.',
            default => 'A page we wrote for you is waiting on you — nothing has gone onto your website.',
        };
    }

    /**
     * The three-and-a-bit About page states (6065, 6067, and the defect half of
     * 5744).
     *
     * ⛔ **THE STATES ARE THE WHOLE POINT AND COLLAPSING THEM IS THE DEFECT
     * 6065 EXISTS ABOUT**: *"you have not told us"*, *"your website is blocking
     * us"* and *"we could not look"* are three different things to be told, and
     * exactly one of them is fixed in a `robots.txt`. Filing the third as the
     * second asks a tenant to re-paste a URL that was never the problem — which
     * they will do, and it will fail again, for ever.
     *
     * ⚠️ **`robots.txt` IS NAMED AND `29` §2 RULE 47 NORMALLY FORBIDS THAT** —
     * `AuthorByline::unreachableAboutPageSentence()` makes the argument at
     * length and it is the same one: the file is **theirs**, on their server,
     * and it is the only thing in the sentence anybody can act on.
     *
     * ⚠️ **THE WORDING IS DELIBERATELY SHORTER THAN `AuthorByline`'s.** That
     * one is a panel on `Account\Locations` explaining a live block; this is one
     * line in a list of a hundred. Same fact, same order of states, and a reader
     * who wants the instruction finds it where the location is.
     *
     * @param  array<string, mixed>  $bag
     */
    private static function aboutPage(array $bag): string
    {
        if (self::flag($bag, 'about_url_confirmed') === false) {
            return 'We need your About page before we can publish anything new for you.';
        }

        if (self::flag($bag, 'about_page_blocked_by_robots') === true) {
            return 'Your website is telling us not to read your About page, so we have not published anything new. '
                .'The setting is in a file called robots.txt on your website.';
        }

        if (self::flag($bag, 'about_page_reachable') === false) {
            return 'We could not read your About page just now, so we have not published anything new. We will keep trying.';
        }

        // Reachable, allowed, confirmed — and it still gave us no name to sign
        // with. The honest remaining sentence, and the one arm `AuthorByline`
        // has no panel for.
        return 'We could not find a name on your About page to sign new pages with, so we have not published anything new.';
    }

    /**
     * The monthly publishing cap, in the two counts the verdict actually
     * counted.
     *
     * ⚠️ **THE FIGURES ARE PRINTED AND THE REGISTRY KEY IS NOT.**
     * `metadata['cap']` is `content.volume.max_new_pages_per_month` — a row
     * name, which is our machinery and `29` §2 rule 47's own example of what may
     * never reach an owner. What it is *for* — posts or pages — is the half a
     * person can use, so the key picks the noun and never appears.
     *
     * ⚠️ **`published` AND `allowed` ARE COUNTS THIS SYSTEM MADE**, so
     * `ClaimLawTest`'s *no customer-facing string states a figure it did not
     * count* is satisfied by construction rather than by exemption:
     * `PublishingVolume::verdict()` counts published pages against a registry
     * cap and puts both in the bag.
     *
     * @param  array<string, mixed>  $bag
     */
    private static function capSpent(array $bag): string
    {
        $noun = self::text($bag, 'cap') === PublishingVolume::MAX_POSTS_KEY ? 'posts' : 'pages';

        $published = self::count($bag, 'published');
        $allowed = self::count($bag, 'allowed');

        if ($published === null || $allowed === null) {
            return 'We have published as many '.$noun.' as this month allows, so the rest are waiting for next month.';
        }

        return 'We have published '.$published.' of the '.$allowed.' '.$noun
            .' this month allows, so the rest are waiting for next month.';
    }

    /**
     * The daily self-audit's pause, named by what it found (`16` §15.3).
     *
     * ⚠️ **THE FIRST FINDING, WHICH IS THE ONE THE PAUSE IS RECORDED UNDER.**
     * `ContentSelfAudit` stores `findings[0]` in
     * `locations.content_generation_pause_reason` and files the whole list in
     * the bag, so taking the first here is the same fact the row itself carries
     * rather than a choice made twice.
     *
     * ⚠️ **A FINDING THIS ENUM DOES NOT KNOW FALLS BACK TO THE GENERAL
     * SENTENCE** rather than to nothing. `ContentAuditFinding` has two cases and
     * `16` §15.3 lists five, so a third is a question of when and not whether —
     * and the general sentence is true of all five.
     *
     * @param  array<string, mixed>  $bag
     */
    private static function selfAudit(array $bag): string
    {
        $findings = $bag['findings'] ?? null;
        $first = is_array($findings) && isset($findings[0]) && is_string($findings[0])
            ? ContentAuditFinding::tryFrom($findings[0])
            : null;

        return match ($first) {
            ContentAuditFinding::ZeroImpressions => 'We have stopped writing new pages for one of your locations: pages we published there are not reaching anybody in search.',
            ContentAuditFinding::NearDuplicateCluster => 'We have stopped writing new pages for one of your locations: pages we published there are too much like each other.',
            default => 'We have stopped writing new pages for one of your locations until something is put right.',
        };
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private static function text(array $bag, string $key): ?string
    {
        $value = $bag[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * ⚠️ **THREE-VALUED ON PURPOSE.** *Absent* and *false* are different
     * answers here: a bag with no `about_url_confirmed` is one this reader does
     * not understand, and treating it as "they have not told us" would print an
     * instruction about a page that may be perfectly fine.
     *
     * @param  array<string, mixed>  $bag
     */
    private static function flag(array $bag, string $key): ?bool
    {
        $value = $bag[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * ⚠️ **A NEGATIVE OR NON-NUMERIC COUNT IS REFUSED RATHER THAN PRINTED.**
     * These two numbers go into a sentence an owner reads as a fact about their
     * own account, and `jsonb` guarantees nothing about what is in a bag.
     *
     * @param  array<string, mixed>  $bag
     */
    private static function count(array $bag, string $key): ?int
    {
        $value = $bag[$key] ?? null;

        return is_int($value) && $value >= 0 ? $value : null;
    }
}
