<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Location;
use App\Services\Actuation\AdapterHealth;
use App\Services\Actuation\AdapterOutcome;
use App\Services\Actuation\ChangeSet;
use App\Services\Actuation\FieldSupport;
use App\Services\Actuation\LogCmsAdapter;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteSnapshot;

/**
 * The seam every content-management system sits behind — doc `41` Part 2,
 * D-164.
 *
 * *"A `CmsAdapter` interface ships in code (connect · health · write change set
 * · snapshot · rollback · uninstall). The WordPress plugin is its first
 * implementation. Future platform apps implement the same interface — no new
 * adapter ships without its own doc, build row, and app-store compliance
 * review."* Six of the verbs below are that sentence, in the order it names
 * them.
 *
 * ⚠️ **THERE WERE SIX AND THERE ARE NINE — ADDED 2026-08-20 (5770–5772), AND
 * THE THREE ARE NOT A GENERALISATION.** Doc `41`'s list assumes a page that
 * already exists: it has *write change set* and *rollback* and no way to say
 * *this page does not exist yet*, which decision 5753 found is what a growth
 * page always is. {@see self::createPage()} and {@see self::unpublishPage()} are
 * that case and its undo; {@see self::fieldSupport()} is the question
 * `Publishing` was not asking, which is why it named a field this adapter
 * refuses. **Every one of the three is reachable from a caller in `app/` on the
 * day it lands** — 272's rule, on an interface rather than a column, and the
 * reason no fourth was added *"while we are here"*.
 *
 * ⛔ **T1 IS WORDPRESS-ONLY IN V1 AND THE INTERFACE IS WHY THAT IS SAYABLE.**
 * Doc `32` Part 1 and the archived Master both promise "WordPress / Shopify /
 * Wix / Webflow"; `41` Part 2 corrects them, because the other three are
 * app-store submissions with their own review cycles, OAuth models and
 * maintenance load rather than plugins. **The correction is the reason this
 * interface exists** — so the promise can be narrowed without the code having
 * assumed WordPress everywhere.
 *
 * ## ⛔ "THE ONLY IMPLEMENTATION TODAY TRANSMITS NOTHING" WAS TRUE AND IS NOT —
 * CORRECTED 2026-08-20 (6184)
 *
 * This heading read exactly that, and under it: *"{@see LogCmsAdapter} is it,
 * and that is slice A's deliverable rather than a gap: **nothing in this slice
 * touches a real website.** … The live WordPress adapter is slice G, and
 * `BUILD-PLAN` §2.11 puts it behind the measurement and auto-rollback slice
 * deliberately."* **There are two implementations.**
 * `App\Services\Actuation\WordPress\WordPressAdapter` landed with slice
 * F1 on 2026-08-19 and reaches a tenant's own site over WordPress core's REST
 * API; slice H — measure and auto-rollback — merged 2026-08-20.
 *
 * ⚠️ **WHAT SURVIVES IS THE PRECEDENT AND NOT THE COUNT.** `LogTexter` is still
 * the shape being copied: a driver that reaches nobody is what makes every later
 * slice testable without a vendor, and it is still the default everywhere. **But
 * which adapter a given install has bound is a fact about that install** — an
 * environment variable read at config-cache time — and no docblock here can
 * see it. `CMS_DRIVER=wordpress` was deployed to production for part of
 * 2026-08-20 with nothing in this repository able to say so (5913, 6121), which
 * is why the sentence above is corrected rather than re-pointed at a newer
 * count that would go stale the same way.
 *
 * ## A refusal is a return value
 *
 * ⚠️ Every verb here answers rather than throws, on {@see VoiceProvider}'s rule.
 * A tenant's site being unreachable, a credential having been revoked inside
 * WordPress, or a plugin having been deleted are ordinary conditions on somebody
 * else's property, and the caller decides what each means. The one thing no
 * caller may do is record a change as applied on the strength of a failed write.
 *
 * ## What is deliberately absent
 *
 * ⛔ **THERE IS NO `execute(string $php)`, NO `installPlugin()` AND NO
 * `updateSelf()`, AND THAT IS A RULE RATHER THAN AN OVERSIGHT.** `CLAUDE.md`:
 * *"The WordPress plugin uses scoped credentials, never full admin, and never
 * remote code execution."* An interface method is a promise every future adapter
 * is asked to keep, so this is exactly where such a verb would be generalised
 * into existence — `VoiceProvider`'s missing `dial()` is the same argument on
 * the same shape.
 *
 * ⛔ **AND THERE IS NO `deletePage()`, WHICH IS THE SAME RULE ABOUT THE VERB A
 * CREATION MAKES LOOK REASONABLE** (5771). This platform can now bring a page
 * into existence on somebody else's website, and the tidy inverse of `create` is
 * `delete`. **It never deletes anything on a customer's website.** Reverting a
 * creation is {@see self::unpublishPage()} — the page leaves the public site and
 * the words survive in the owner's own WordPress, where they can find them.
 * Byte-identical restoration is structural rather than aspirational: a visitor
 * sees what they saw before, and the owner keeps what we wrote. Both halves are
 * held by the same `Architecture\ActuationTest` lint that refuses `execute()`.
 */
interface CmsAdapter
{
    /**
     * Establish, or re-establish, this platform's write access to a location's
     * site.
     */
    public function connect(Location $location): AdapterOutcome;

    /**
     * Whether that access is still good, asked before anything is attempted.
     */
    public function health(Location $location): AdapterHealth;

    /**
     * Which of these fields this adapter can write, and why not the others.
     *
     * ⛔ **THE PIPELINE ASKS RATHER THAN ASSUMING, AND DECISION 5753 IS WHY.**
     * A change set naming a field the adapter cannot write is refused **whole**
     * (5591, 5532's rule) — correctly, because writing three of four fields
     * records four in `after_snapshot` and puts three on the page. The
     * consequence was that every live publish would have opened a `site_changes`
     * row and failed, because the publisher named a meta description core REST
     * has no way to write.
     *
     * ⚠️ **ANSWER WITHOUT TOUCHING THE NETWORK.** This is asked on every publish;
     * {@see self::health()} is the verb that may cost a round trip.
     *
     * @param  list<string>  $fields
     */
    public function fieldSupport(array $fields): FieldSupport;

    /**
     * Write one change set to the site.
     *
     * ⛔ **NEVER CALLED WITHOUT A `site_changes` ROW OPENED FIRST.**
     * {@see SiteChanges} is the only supported caller,
     * and slice J ships the CI lint that fails the build on an
     * `AutopilotJob` reaching here without one.
     */
    public function writeChangeSet(Location $location, ChangeSet $set): AdapterOutcome;

    /**
     * Read a page's current state, for the fields a change set is about.
     *
     * ⛔ **THIS IS RULE 32's FIRST HALF AND ITS RESULT IS NOT OPTIONAL.** An
     * empty snapshot is an honest answer from a driver that read nothing, and
     * {@see SiteChanges::open()} refuses to open a
     * change set on one.
     *
     * @param  list<string>  $fields
     */
    public function snapshot(Location $location, string $url, array $fields): SiteSnapshot;

    /**
     * Create the page a change set describes, at the address it names.
     *
     * ⛔ **ONLY FOR A CHANGE SET WHOSE `before` IS A RECORDED ABSENCE** —
     * {@see ChangeSet::creating()}, and {@see ChangeSet::isCreation()} is what
     * {@see SiteChanges::apply()} routes on. A creation built on *"we could not
     * read the site"* would put a second page on top of one that already exists
     * (5770); an adapter is entitled to assume the caller looked, and to refuse
     * if what it finds says otherwise.
     *
     * ⛔ **THE PAGE MUST END UP AT THE URL THE CHANGE SET NAMES, OR NOT EXIST.**
     * That URL is what was snapshotted, what a rollback will address, what
     * measurement will read and what was announced to search engines. An
     * implementation that lets the CMS choose a different address — a duplicate
     * slug, a permalink structure with a date in it — must undo its own creation
     * and report failure rather than hand back an address nobody asked for.
     */
    public function createPage(Location $location, ChangeSet $set): AdapterOutcome;

    /**
     * Take a page this platform created back off the public site.
     *
     * ⛔ **THIS PLATFORM NEVER DELETES ANYTHING ON A CUSTOMER'S WEBSITE, AND
     * THIS METHOD IS WHERE THAT PROMISE IS WRITTEN DOWN FOR EVERY ADAPTER THAT
     * COMES AFTER THIS ONE** (5771). Reverting a creation restores the *public*
     * state — a visitor sees exactly what they saw before, which is rule 32's
     * reversibility — **without destroying anything**. The words stay in the
     * owner's own CMS, under their own account, where they can publish them
     * themselves if they disagree with us. On WordPress that is `status: draft`;
     * on any future platform it is whatever means *"off the site, still theirs"*.
     *
     * ⚠️ **AN ADDRESS THAT IS ALREADY NOT PUBLIC IS A SUCCESS, NOT A FAILURE.**
     * The owner having taken the page down themselves reaches the same end state
     * this method exists to produce, and reporting it as a failed revert would
     * leave a `site_changes` row claiming a page is live that is not.
     */
    public function unpublishPage(Location $location, ChangeSet $set): AdapterOutcome;

    /**
     * Put the page back.
     *
     * ⚠️ **TAKES A CHANGE SET RATHER THAN A `SiteChange` MODEL**, so that no
     * adapter — including one written by somebody who has never read this
     * codebase — has a route to the table. {@see ChangeSet::inverted()} is what
     * the caller hands over.
     */
    public function rollback(Location $location, ChangeSet $set): AdapterOutcome;

    /**
     * Give the site back: remove everything this platform installed, and
     * relinquish write access.
     *
     * ⚠️ **PART OF THE INTERFACE RATHER THAN AN OPERATIONS TASK, FOR 4880's
     * REASON.** A deleted customer who leaves us holding write access to their
     * website is the same defect as the Zernio grant nothing revoked — an
     * obligation with no code path, discovered later.
     */
    public function uninstall(Location $location): AdapterOutcome;
}
