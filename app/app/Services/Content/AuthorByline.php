<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\FetchGateway;
use App\Enums\AutopilotActionType;
use App\Enums\PublishRefusal;
use App\Models\ActivityFeedItem;
use App\Models\Business;
use App\Models\Location;
use App\Services\Actuation\SiteProbe;
use App\Services\Fetch\FetchResult;
use App\Services\Tenant\LocationWebsite;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * `29` §2 rule 36, as a gate — `BUILD-PLAN` §2.11.3 slice G.
 *
 * *"Auto-published content carries a real author byline linked to a genuine
 * About page."* Decision 5677 recorded that slice D publishes without one and
 * that it is owed before **G**, the first slice whose writes reach a real
 * website. 5720–5722 and 5729 are the owner's ruling on what it says.
 *
 * ## The ruling, in the three sentences the code has to hold
 *
 *   1. **The tenant's own company name is the byline** (5720), linked to the
 *      About page on their own site. Not ours: *"the vendor reading is refused
 *      and was never the ruling"*.
 *   2. ⛔ **No About page means no publish** (5720). **It is a gate, not a
 *      field.** A location without one does not publish at all.
 *   3. ⚠️ **The check is whether the link resolves** (5729), and that survives
 *      all three overrules — *"whatever name carries the byline, it links
 *      somewhere or the page does not publish"*.
 *
 * ⚠️ **NEITHER OF THE TWO PERMITTED ALTERNATIVES IS BUILT HERE, DELIBERATELY.**
 * A real named person may carry the byline without that person's agreement
 * (5721, an overrule) and an invented persona is permitted for content (5722, an
 * overrule of a refusal). **Neither is implemented and neither is inferred**:
 * 5727's rule is that an overrule authorises what was asked for and not its
 * neighbours, and 5722 names two things it does not settle — a fabricated
 * biography, and where a persona's About link would point. **Building a persona
 * here would have meant inventing one to make a page publish**, which is the one
 * thing this slice was told to stop and report rather than do.
 *
 * ## Two questions, asked in two places, at the two times each is true
 *
 * ⚠️ **{@see self::stored()} IS A FACT ABOUT THE ROW AND COSTS NOTHING**;
 * {@see self::verify()} adds *"and it answers today"* and costs one fetch of the
 * tenant's own website. The split is `WordPressCredentials::isConnected()`'s
 * exactly: a screen may ask the first on every render, and only a queued job may
 * ask the second.
 *
 * ⛔ **THE RESOLUTION CHECK IS AT PUBLISH TIME AND NOT AT PASTE TIME, AND THAT
 * IS ONE CHECK RATHER THAN TWO** (5743). An About page that answered when
 * somebody pasted it says nothing about the morning a redesign 404s it, and rule
 * 36's promise is about the page being published *now*. A confirm-time check as
 * well would be 398's shape — an outer guard that refuses first, leaving this
 * one unfalsifiable — and it would put a fetch of a stranger's website inside a
 * button press. What the paste-time writer checks instead is the thing that
 * cannot change underneath it: that the address is on the site they already
 * confirmed.
 *
 * ⛔ **A FETCH THIS PLATFORM IS NOT ALLOWED TO MAKE IS A REFUSAL, NOT A PASS.**
 * The gateway honours `robots.txt` by a CHECK on `fetch_sources`, so a tenant
 * whose robots file disallows us has an About page we cannot verify — and the
 * conservative direction on somebody else's website is not to publish. ⚠️ **It
 * is raised as a question rather than settled** (5744): the population it
 * describes is small and real, and *"your own robots.txt stops us publishing to
 * your own site"* is a sentence somebody should choose deliberately.
 *
 * ⚠️ **AND THAT QUESTION IS STILL THE OWNER'S — 2026-08-20 (6066).** The
 * exemption was not granted here, and the reason is structural rather than
 * timid: fetching a URL the gateway has refused would need a new parameter on
 * {@see FetchGateway::fetch()}, a contract `40` Part 6 keeps deliberately narrow
 * and that every source in this application reaches. **A per-location exemption
 * cannot be built without first building a general one**, which is the hole a
 * narrow exemption exists to avoid. The ruling costs one method here and one
 * argument there, and it is written up at 6066.
 *
 * ⛔ **WHAT *WAS* CLOSED IS THE HALF THAT NEEDED NOBODY'S RULING** (6065):
 * {@see self::check()} tells a refusal from a broken page, because
 * `AuthorByline` was collapsing a distinction `FetchResult` had gone to the
 * trouble of making and telling a real population a false thing about their own
 * website as a result.
 */
final class AuthorByline
{
    /**
     * The tenant's own confirmed website, as a registered fetch source.
     *
     * ⚠️ **THE SAME ROW SLICE B REGISTERED, RATHER THAN A SECOND ONE.** Its own
     * migration argues the split it did make — `tenant_website` against
     * `subject_website` — on budgets and kill switches, and this is the same
     * traffic to the same hosts on the same terms: a signed-in owner's confirmed
     * site, fetched occasionally. A third row would split a budget for nothing.
     */
    public const string SOURCE = SiteProbe::SOURCE;

    public function __construct(private readonly FetchGateway $gateway) {}

    /**
     * The byline this location would carry, from the row alone.
     *
     * ⚠️ **NO HTTP, SO A SCREEN MAY ASK IT.** Null means *"there is nothing to
     * sign with"* — no confirmed About page, or a company with no name — and a
     * caller that needs *"and it works"* wants {@see self::verify()}.
     */
    public function stored(Location $location): ?PageByline
    {
        $this->assertBelongsToTenant($location);

        $about = $location->about_url;

        if ($about === null || $location->about_url_confirmed_at === null) {
            return null;
        }

        $name = trim((string) Business::query()->find(Tenancy::idOrFail())?->name);

        // ⚠️ **A BUSINESS WITH NO NAME CANNOT BE A BYLINE.** `businesses.name` is
        // required at provisioning, so this arm is defence rather than a case —
        // but signing a page "Published by" and nothing is worse than not
        // publishing it, and 5720's ruling is a *name*.
        return $name === '' ? null : new PageByline($name, $about);
    }

    /**
     * The byline, if the page it points at answers right now.
     *
     * ⛔ **THIS IS THE GATE AND ITS NULL IS A REFUSAL TO PUBLISH.** Every arm
     * that is not a successful fetch — a 404, a redirect the gateway would not
     * follow, a timeout, a robots refusal, a spent rate budget, the kill switch
     * — returns null, and {@see Publishing} turns that into
     * {@see PublishRefusal::NoAuthorByline} and tells the owner. A
     * transient refusal costs one attempt; the sweep tries again.
     */
    public function verify(Location $location): ?PageByline
    {
        return $this->check($location)->byline;
    }

    /**
     * The same question, with the reason kept.
     *
     * ⛔ **THE GATE IS UNCHANGED AND THAT IS DELIBERATE** (5729, 6065).
     * {@see self::verify()} is one line of this method and still answers null for
     * a refusal exactly as it does for a 404, so nothing publishes that could not
     * publish yesterday. What this adds is the fact the refusal carries.
     *
     * ⛔ **"WE DID NOT LOOK" IS NOT "WE LOOKED AND FOUND NOTHING"**, and
     * {@see FetchResult::wasRefused()} exists to say so — `40` §6.4 requires
     * every owner surface to keep the two apart, and this method was throwing the
     * distinction away one line after the gateway made it. **The cost was a false
     * sentence to a real population**: a tenant whose own `robots.txt` disallows
     * us was told to give us an About page they had already given us, would give
     * it again, and would fail again (5744, and the defect half of it at 6065).
     *
     * ⛔ **AND IT DOES NOT FETCH ANYWAY, WHICH IS 5744's OWN RULING TO MAKE
     * AND NOT THIS FILE'S** (6066). A blanket exemption would need a new
     * parameter on {@see FetchGateway::fetch()} — the one contract `40` Part 6
     * exists to keep narrow, reached by every source in the application — which
     * is precisely the hole a narrow exemption is supposed to avoid being.
     */
    public function check(Location $location): BylineCheck
    {
        $byline = $this->stored($location);

        if ($byline === null) {
            return BylineCheck::missing();
        }

        $result = $this->gateway->fetch(self::SOURCE, $byline->aboutUrl);

        if ($result->successful()) {
            return BylineCheck::verified($byline);
        }

        // ⚠️ **EVERY REFUSAL AND NOT ONLY ROBOTS.** A spent rate budget and a
        // killed source are also *"we did not look"*, and both are transient in
        // a way a 404 is not — telling somebody their About page has stopped
        // working because we ran out of fetches for the hour would be as false
        // as the robots case, for the same reason.
        // ⛔ **AND ONLY A RULE THE HOST PUBLISHED COUNTS AS THEIRS** (9480-9499).
        // `RobotsPolicy` answered one `false` for *"their robots.txt says no"*
        // and *"we could not fetch their robots.txt at all"*, so a timeout, a
        // 500 or a WAF's 403 at their host arrived here as
        // `ownRobotsRefused: true` — and `unreachableAboutPageSentence()` below
        // then told them, in writing, that a file on their own server was
        // turning us away and to have somebody edit it.
        // `FetchRefusalReason::namesTheOriginsOwnRule()` is the question now,
        // and the second copy of the literal this class used to hold is gone
        // with it.
        return $result->wasRefused()
            ? BylineCheck::notLookedAt($result->refusalReason?->namesTheOriginsOwnRule() === true)
            : BylineCheck::missing();
    }

    /**
     * What to tell an owner whose own website is refusing us their About page —
     * on a screen, with no HTTP.
     *
     * ⛔ **A REFUSAL NOTHING SURFACES IS 272 WITH A GREEN SUITE** (5836's words).
     * The gate files an owner action item and **nothing in `app/` renders the
     * activity feed**, so before this method the whole of what a blocked tenant
     * could find out was that publishing had stopped. This is read on the screen
     * where their About page already is.
     *
     * ⛔ **IT READS THE LAST ATTEMPT AND NEVER FETCHES** (5599's rule, which
     * `Locations` states twice already): health is HTTP and this is asked once
     * per location on every render. What it reads is the fact the publish path
     * recorded.
     *
     * ⛔ **AND IT CLEARS ITSELF AGAINST A LATER PUBLISH RATHER THAN AGAINST A
     * CLOCK.** The action item is filed at most once per location per month, so
     * a tenant who fixed their robots file would otherwise read *"your website
     * is blocking us"* for weeks after we had published for them. A successful
     * publish files `ContentPublished` on the same location, so a later one of
     * those is proof the block is over — which is a fact rather than a timeout.
     */
    public function unreachableAboutPageSentence(Location $location): ?string
    {
        $this->assertBelongsToTenant($location);

        // ⚠️ **ASKED ONLY WHERE THE QUESTION CAN HAVE AN ANSWER.** No confirmed
        // About page means nothing was ever fetched, so no refusal was ever
        // recorded — and this method is called once per location on every render
        // of a screen that lists them all.
        if ($location->about_url_confirmed_at === null) {
            return null;
        }

        $blocked = ActivityFeedItem::query()
            ->where('location_id', $location->id)
            ->where('action_type', AutopilotActionType::OwnerActionNeeded->value)
            ->whereRaw("metadata->>'needs' = ?", ['about_page'])
            ->whereRaw("metadata->>'about_page_blocked_by_robots' = ?", ['true'])
            // ⚠️ **ORDERED AND COMPARED BY `id`, NEVER BY `created_at`.**
            // `activity_feed` is append-only, so its ids are insertion order and
            // are never null — where a descending sort on a nullable timestamp
            // relies on Postgres putting NULLs first, which `ConventionsTest`
            // fails the build over and which caught this method's first draft.
            // Two rows written in the same second would also tie on the
            // timestamp and not on the id, and the tie here decides whether an
            // owner is told their website is blocking us.
            ->orderByDesc('id')
            ->first();

        if ($blocked === null) {
            return null;
        }

        $published = ActivityFeedItem::query()
            ->where('location_id', $location->id)
            ->where('action_type', AutopilotActionType::ContentPublished->value)
            ->orderByDesc('id')
            ->first();

        if ($published !== null && $published->id >= $blocked->id) {
            return null;
        }

        // ⚠️ **`robots.txt` IS NAMED, WHICH `29` §2 RULE 47 NORMALLY FORBIDS.**
        // The rule is that our machinery never reaches an owner's screen — and
        // this file is **theirs**, on their server, and it is the only thing in
        // the sentence they or their web person can act on. `PixelInstall` names
        // a script tag on the same reasoning: a name somebody has to type is not
        // an implementation detail, it is the instruction.
        return 'Your website is telling us not to read your About page, so we cannot check the link '
            .'we have to put on anything we publish — and until we can, we will not publish. The '
            .'setting is in a file called robots.txt on your website. Whoever looks after your site '
            .'can let us in, or you can point us at a different page that says who you are.';
    }

    /**
     * ⚠️ **{@see LocationWebsite}'s WRONG-TENANT REFUSAL, AT A THIRD ADDRESS.** A
     * byline read for another tenant's location would sign this tenant's page
     * with somebody else's company name.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A byline read here would sign a page with '
            .'a company that is not the publisher.',
        );
    }
}
