<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

use App\Contracts\CmsAdapter;
use App\Enums\AdapterOutcomeState;
use App\Exceptions\WordPressRequestFailed;
use App\Models\Location;
use App\Services\Actuation\ActuationActor;
use App\Services\Actuation\AdapterHealth;
use App\Services\Actuation\AdapterOutcome;
use App\Services\Actuation\ChangeSet;
use App\Services\Actuation\FieldSupport;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteSnapshot;

/**
 * `CmsAdapter` over WordPress core's own REST API — `BUILD-PLAN` §2.11.3 slice
 * F1.
 *
 * ⛔ **NO PLUGIN. THAT IS THE SLICE.** §2.11.5 conflict 7: `19` §3.3's
 * credential line is *"Application Passwords / scoped REST credentials — never
 * full admin"*, and Application Passwords are WordPress **core**, shipped since
 * 5.6. Publishing a page to a tenant's WordPress needs no plugin of ours at all
 * — it needs an owner-pasted Application Password over HTTPS. The plugin (F2)
 * still has three jobs core REST cannot do, and all three belong to E and L:
 * the IndexNow key file, the `robots.txt` sitemap line, and the seven speed
 * fixes with the in-WP undo (5581).
 *
 * ## Nothing in this deployment resolves to this class
 *
 * ⚠️ **`CMS_DRIVER` STILL SEEDS `log`, AND THAT IS THE WHOLE OF WHAT KEEPS THIS
 * CLASS OUT OF PRODUCTION TODAY.** `BUILD-PLAN` §2.11 puts a live adapter behind
 * measurement and auto-rollback deliberately: write access to a stranger's site
 * without a proven revert is the liability `29` rule 32 names, and this slice
 * does not have the revert *proven*, only *written*.
 *
 * ⛔ **"`actuation.enabled` DOES NOT EXIST" WAS TRUE WHEN F1 WROTE IT AND HAS
 * BEEN FALSE SINCE 2026-08-19 — CORRECTED 2026-08-20 (5665, 5774).** The
 * paragraph here read *"it appears nowhere in `DefaultsManifest`, `app/`,
 * `config/` or `database/` … slice D builds the key; until it does, `CMS_DRIVER`
 * is the only thing standing between this class and a customer's website"*, and
 * it was right to say so: a safety layer asserted before it is true is 314–316's
 * failure. **Slice D built it** — `Publishing::SWITCH_KEY`, seeded `false`,
 * naming slice H as what it waits for — so there really are two switches now,
 * and `Publishing::canWriteToSite()` asks a third question on top of them: the
 * bound adapter's own `health()`. ⚠️ **The correction is kept rather than the
 * sentence replaced**, because the failure shape it names is the one this file
 * is most likely to acquire again: the fix for *"a switch that does not exist"*
 * is to build the switch, never to describe it more confidently.
 *
 * ⚠️ **AND NO TEST IN THIS REPOSITORY TALKS TO A REAL WORDPRESS, WHICH IS SAID
 * OUT LOUD RATHER THAN IMPLIED** (352/397). Every test here fakes the HTTP layer
 * with fixtures built from WordPress's published schema and core's own source.
 * That pins **our half of the wire** — the routes, the arguments, the field
 * shapes, the refusals — and says nothing about the other half. A test named
 * *"publishes to WordPress"* that faked WordPress would be worse than no test,
 * because it would read as coverage of the thing it cannot reach. The other half
 * is a staging checklist against a real install and it belongs to slice G; the
 * single item on it that most needs running is
 * {@see WordPressRestClient}'s write read-back comparison.
 *
 * ## Every write goes through `SiteChanges` first, and this class cannot skip it
 *
 * ⚠️ **THERE IS NOTHING HERE THAT OPENS A CHANGE SET, AND THAT IS THE POINT.**
 * {@see SiteChanges} is the only writer of `site_changes` and the only supported
 * caller of `writeChangeSet()`; it snapshots, then opens a row refusing an empty
 * before-snapshot, then applies. An adapter that could open its own row would be
 * a second route to a change nobody can undo.
 */
final class WordPressAdapter implements CmsAdapter
{
    /**
     * Why a field this platform wants to write is not writable here.
     *
     * ⚠️ **A FIXED STRING PER FIELD, BECAUSE IT IS STORED AND SHOWN.** It lands
     * on `site_changes.withheld_fields` and in the append-only audit entry, so a
     * reader six months from now can tell *"WordPress cannot do this"* from
     * *"nobody asked for it"* without reading this class.
     *
     * @var array<string, string>
     */
    private const array REFUSALS = [
        'meta_description' => 'wordpress: core REST has no meta description — it is an SEO plugin\'s registered post meta and reaches REST only where that plugin passed show_in_rest (5591)',
    ];

    /**
     * The failures that mean *the request was accepted and the page is now
     * wrong* — the only ones a compensating write answers.
     *
     * ⛔ **A LIST RATHER THAN ONE LITERAL, AND THE LIST IS WHY THIS IS A
     * CONSTANT** (5987). It was `$e->reason !== 'unreadable:content_filtered'`
     * inline, which was correct for the one detector that existed; the read-back
     * now has three, and the arm that decides whether to put a page back must
     * not be the arm somebody forgets to widen. **Everything not on this list is
     * a write that did not land**, and a restore issued after one is a second
     * edit to a page nobody touched — on a `5xx`, to a site that is already
     * unwell.
     *
     * ⚠️ **`unreadable:read_back_response` IS DELIBERATELY ABSENT AND IS THE
     * HARDEST OF THE FOUR.** *"We wrote and then could not read the page"*
     * leaves us not knowing whether the write landed, so neither answer is safe:
     * putting the `before` values back could overwrite a write that never
     * happened with content the page already has (harmless) **or** could itself
     * fail on the same unreachable site (likely). It is reported as a plain
     * failure, `applied_at` is never stamped, and the change set stays open for
     * the queue to retry — which is the state that describes what is actually
     * known.
     *
     * @var list<string>
     */
    private const array LANDED_BUT_WRONG = [
        // The site filtered our values as it saved them — `kses` (5588).
        'unreadable:content_filtered',
        // The site rewrote them after saving them, in a hook core fires after
        // it captured the object it answers with (5980).
        'unreadable:content_rewritten',
        // The write took the page out of public view.
        'unreadable:unpublished_by_site',
    ];

    public function __construct(
        private readonly WordPressCredentials $credentials,
        private readonly WordPressRestClient $client,
    ) {}

    /**
     * Re-establish access using the credential already stored.
     *
     * ⚠️ **THIS DOES NOT TAKE A PASSWORD, BECAUSE THE INTERFACE DOES NOT AND
     * SHOULD NOT.** Pasting one is an owner act on a screen with a form request
     * behind it; `WordPressCredentials::connect()` is what that screen calls.
     * What `CmsAdapter::connect()` means here is the other half — *is the thing
     * we already hold still good* — which is the question a publishing job asks.
     */
    public function connect(Location $location): AdapterOutcome
    {
        $connection = $this->credentials->verify($location);

        return $connection->ok
            ? AdapterOutcome::ok('wordpress: connected as '.implode(', ', $connection->roles))
            : AdapterOutcome::failed('wordpress: '.$connection->detail());
    }

    /**
     * Whether this platform can write to the site right now.
     *
     * ⛔ **IT RE-RUNS §19.7's GATE RATHER THAN TRUSTING THE STORED ANSWER.** A
     * WordPress role can be changed inside WordPress at any moment, and a
     * connection recorded as least-privilege on the day it was made says nothing
     * about today. `LogCmsAdapter` answers *not writable* because it writes
     * nowhere; this answers it because the credential may have become one this
     * platform refuses to hold.
     */
    public function health(Location $location): AdapterHealth
    {
        // ⚠️ **AND IT IS NO LONGER THE ONLY PLACE THE GATE RUNS — 2026-08-20
        // (5985).** This method's own docblock made the right argument and drew
        // the wrong boundary: a role can change after *this* answer just as
        // easily as after the connection, and nothing called it at all on the
        // revert path. The gate now also runs immediately before every write
        // ({@see self::gatedSite()}); what survives here is the question a
        // caller asks *before deciding* to attempt anything, which is what `41`
        // Part 2 lists it as.
        $connection = $this->credentials->verify($location);

        return new AdapterHealth(
            $connection->ok,
            $connection->ok
                ? 'wordpress: writable as '.implode(', ', $connection->roles)
                : 'wordpress: '.$connection->detail(),
        );
    }

    /**
     * Read a page's current state for the fields a change set is about.
     *
     * ⛔ **THE REAL PRIOR STATE, OFF THE LIVE SITE, IN `edit` CONTEXT.** Decision
     * 5528 is why this is not a stub: a plausible-looking placeholder would
     * satisfy `SiteChanges::open()`'s rule-32 guard with a value describing no
     * website at all, turning a promise about reversibility into a shape check
     * on the one path where being wrong means a stranger's page cannot be put
     * back. An empty snapshot returned here is an honest failure and `open()`
     * refuses it, exactly as it refuses the log driver's.
     *
     * ⛔ **THREE OUTCOMES, AND THE ONE THAT MUST NOT COLLAPSE INTO ANOTHER IS
     * *ABSENT* AGAINST *UNREADABLE*** (5770). *"We asked the site and no
     * published page claims this URL"* is what a **creation** proceeds from;
     * *"we could not see the site"* must never be read as it, because the page
     * we would then create may be created on top of one that is already there,
     * or on a site that is merely down for an hour. Only
     * {@see WordPressRestClient::locate()} returning `null` — both collections
     * queried, both answered, nothing matching — is absence. **Every exception
     * is unreadable, including a `404`**, which is what an absent REST endpoint
     * and an absent page used to have in common.
     *
     * @param  list<string>  $fields
     */
    public function snapshot(Location $location, string $url, array $fields): SiteSnapshot
    {
        $site = $this->credentials->siteFor($location);

        if ($site === null) {
            // Nothing is connected, so nothing looked. **Not `absent()`** — see
            // the method docblock.
            return SiteSnapshot::unreadable();
        }

        $supported = $this->supported($fields);

        if ($supported === []) {
            // Nothing this adapter can read, so nothing to ask the site for. An
            // empty snapshot is the honest answer and `open()` refuses it.
            return SiteSnapshot::unreadable();
        }

        try {
            $post = $this->client->locate($site, $url, $supported);
        } catch (WordPressRequestFailed) {
            // ⚠️ **A FAILED READ IS AN UNREADABLE SNAPSHOT, NOT AN EXCEPTION**,
            // and the caller is protected by that rather than harmed: `open()`
            // refuses an empty before-snapshot, so a page we could not read is a
            // change set that never exists.
            //
            // ⛔ **AND IT IS NEVER `absent()`.** A `404` from the API, an
            // ambiguous slug, a revoked credential and a site that is down all
            // arrive here, and every one of them is *"we could not see"* rather
            // than *"there is nothing there"*.
            return SiteSnapshot::unreadable();
        }

        // ⚠️ **THE COLLECTIONS ANSWERED AND NO PUBLISHED PAGE CLAIMS THIS URL.**
        // That is an observation about the site, and it is the only state a
        // creation may be built on — {@see WordPressRestClient::locate()}
        // returns `null` for exactly this and throws for everything else, which
        // is the whole of decision 5770's separation.
        return $post === null
            ? SiteSnapshot::absent()
            : SiteSnapshot::read($post->fields);
    }

    /**
     * Write one change set to the site.
     *
     * ⛔ **`28` §4.4 — "change set aborts atomically, no partial state" — AND
     * WORDPRESS HAS NO TRANSACTION TO GIVE US.** What makes it true here is
     * two facts about core REST, both verified rather than assumed:
     *
     *   1. **One change set is one request.** Core's update endpoint takes
     *      `title`, `content` and `excerpt` together and applies them in a single
     *      `wp_update_post()`, so a transport failure leaves the page untouched.
     *      There is no half-written page to clean up because there was never a
     *      second call to fail.
     *   2. **A `200` is not proof.** A credential without `unfiltered_html` has
     *      its markup silently stripped on save and gets a `200` back with the
     *      sanitised value — core's `kses_init()`, quoted in full in
     *      {@see WordPressRestClient}. That *is* a partial state: some fields
     *      took and some did not, and `applied_at` would be stamped on it.
     *
     * ⛔ **AND A THIRD, WHICH IS 5819(b) AND WHICH THE UPDATE RESPONSE CANNOT
     * SHOW: THE SITE CAN REWRITE OUR VALUES AFTER SAVING THEM** (5980). Core
     * prepares that response from a post object it fetched **before**
     * `rest_after_insert_*` and `wp_after_insert_post` fire, so a plugin working
     * in either hook changes the row and leaves the answer we read untouched.
     * The detector is therefore a **second request** that re-reads the page,
     * and {@see WordPressRestClient} carries the source that proves it.
     *
     * **So the abort is a compensating write and this is where it happens.** The
     * read-backs are the detectors; putting the `before` values back is the
     * abort; and a restore that itself fails is reported as its own outcome
     * rather than folded into the first, because those two leave the owner's
     * page in different states and only one of them needs a human.
     * {@see self::LANDED_BUT_WRONG} is which failures reach it and why the
     * others must not.
     */
    public function writeChangeSet(Location $location, ChangeSet $set): AdapterOutcome
    {
        return $this->edit($location, $set, expectStored: null);
    }

    /**
     * Which of these fields core REST can write, and why not the others.
     *
     * ⛔ **THE ANSWER `Publishing` WAS NOT ASKING FOR** (5753(a), 5772). It named
     * `meta_description` in every change set it built, and this adapter refuses a
     * set containing an unwritable field **whole and by name** — both halves
     * correct, and together they meant every live publish would open a
     * `site_changes` row and fail. The field now comes out of the set before it
     * is built, with `self::REFUSALS`' reason recorded beside it.
     *
     * ⚠️ **NO NETWORK CALL, AND THE ANSWER IS THE SAME FOR EVERY SITE.** Whether
     * an SEO plugin has exposed its meta over REST is genuinely per-site, and
     * asking would put a request to a customer's server inside every publish —
     * 5599's argument, one verb along. **The per-site answer is F2's**, along
     * with the plugin that makes it true.
     *
     * @param  list<string>  $fields
     */
    public function fieldSupport(array $fields): FieldSupport
    {
        $writable = [];
        $refused = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, WordPressRestClient::WRITABLE_FIELDS)) {
                $writable[] = $field;

                continue;
            }

            $refused[$field] = self::REFUSALS[$field] ?? 'wordpress: not writable over core REST';
        }

        return new FieldSupport($writable, $refused);
    }

    /**
     * Create the page this change set describes.
     *
     * ⛔ **A GROWTH PAGE IS A PAGE THAT DOES NOT EXIST YET, AND THAT IS THE HALF
     * OF DECISION 5753 NOTHING COULD DO** (5773). `writeChangeSet()` edits a
     * published page it can resolve by slug; a page nobody has published
     * resolves to nothing, so before today the publish refused with
     * `SiteUnreadable` and the row's own gate could never be reached on T1.
     *
     * ⚠️ **THE RACE BETWEEN THE SNAPSHOT AND THIS CALL IS CLOSED BY THE
     * PERMALINK CHECK RATHER THAN BY A SECOND LOOKUP.** An owner can publish a
     * page at the same address in between; core then makes our slug unique by
     * appending `-2`, the created page answers on an address we did not ask for,
     * and {@see WordPressRestClient::create()} unpublishes its own creation and
     * fails. A second `locate()` here would be a guard that narrows the window
     * without closing it, and 398's rule is that the guard which cannot fail is
     * the one nobody drives.
     */
    public function createPage(Location $location, ChangeSet $set): AdapterOutcome
    {
        if (! $set->isCreation()) {
            // The caller has the routing wrong. Creating a page whose change set
            // recorded a prior state would leave the page it described untouched
            // and put a second one beside it.
            return AdapterOutcome::failed('wordpress: this change set is not a creation');
        }

        $site = $this->gatedSite($location);

        if ($site instanceof AdapterOutcome) {
            return $site;
        }

        $fields = $this->writableFields($set->after);

        if ($fields === null) {
            return AdapterOutcome::failed('wordpress: change type not writable over core REST');
        }

        try {
            $post = $this->client->create($site, $set->url, $fields);
        } catch (WordPressRequestFailed $e) {
            return AdapterOutcome::failed('wordpress: '.$e->reason);
        }

        // ⚠️ **THE PAGE'S IDENTITY TRAVELS WITH THE SUCCESS** (5966, 6141).
        // `SiteChanges::apply()` stores it on `site_changes.written_page_ref`,
        // and it is what lets {@see self::unpublishPage()} find this page again
        // thirty days later after an owner has renamed its slug — the case 6040
        // reported as a page taken down.
        return AdapterOutcome::ok(
            'wordpress: '.$post->type.'/'.$post->id.' created',
            $post->type.'/'.$post->id,
        );
    }

    /**
     * Take a page this platform created back off the public site.
     *
     * ⛔ **DRAFT, NEVER DELETE** (5771). The owner keeps every word of it in
     * their own WordPress; what a visitor sees is what they saw before the page
     * existed, which is rule 32's reversibility on the only terms a creation can
     * offer it.
     *
     * ⛔ **"A PAGE THAT IS ALREADY NOT PUBLISHED IS A SUCCESS" WAS THE RULE AND
     * IT IS NOW HALF OF ONE — CORRECTED 2026-08-20 (6040, 6141).** The paragraph
     * here read: *"The owner deleting or unpublishing it themselves reaches the
     * same end state, and reporting that as a failed revert would leave a
     * `site_changes` row saying a page is live that is not — and slice J
     * offering an Undo for it."* **Every word of that is still true and it was
     * applied to the wrong observation.** What this method had in hand was
     * {@see WordPressRestClient::locate()} returning `null`, which means *"no
     * published page answers at that address"* — and an owner editing a slug,
     * changing the permalink structure, adding or dropping `www.`, reparenting
     * the page or moving domain produces exactly that answer **with our page
     * still live and still indexed under their name**. The platform reported it
     * removed, told them *"Undone — the page is off your website"*, closed the
     * row, and switched off `SiteMeasurements::dueForRevert()`'s nightly retry —
     * which filters `whereNull('rolled_back_at')` — built for precisely this
     * case. ⚠️ **A passing test asserted the arm as correct**, naming the assumed
     * cause as though it were the only one.
     *
     * ## Three questions, asked in this order
     *
     *   1. **The id we recorded when we created the page.** An id does not move
     *      when an address does. `written_page_ref` (5966, 6141) is what makes
     *      this question askable at all, and it answers the common case
     *      outright: published, so take it down; not published, so it is already
     *      down, and *that* is the success the old paragraph describes.
     *   2. **The address, as before**, for a row written before the column
     *      existed and for an adapter that records no reference.
     *   3. **Both together.** `locate()` returning `null` proves both
     *      collections were queried and both answered, so the route is alive, so
     *      a `404` on the id is a fact about the **post** and not about the
     *      endpoint: the page was permanently deleted — which needs
     *      `force=true` and is somebody doing it on purpose.
     *
     * ⛔ **AND WITH NO RECORDED ID AND NOTHING AT THE ADDRESS, THE OUTCOME IS
     * `Unverified`** — {@see AdapterOutcomeState}. Not a failure to apologise
     * for and not a success: nothing on the site changed, our page may well
     * still be on it, and the row stays open so the nightly retry can ask again.
     *
     * ⚠️ **`title` IS THE ONLY FIELD ASKED FOR, BECAUSE THIS READS NOTHING
     * BACK.** `locate()` needs a field list to build a post, and every page in
     * `edit` context has a `title.raw`; asking for the change set's own fields
     * would make an unrelated missing value look like an absent page.
     */
    public function unpublishPage(Location $location, ChangeSet $set): AdapterOutcome
    {
        $site = $this->gatedSite($location);

        if ($site instanceof AdapterOutcome) {
            return $site;
        }

        // ⛔ **BY THE ID WE RECORDED FIRST, AND BY THE ADDRESS ONLY AFTERWARDS**
        // (6141). See the method docblock: the address is the thing that moves.
        // ⛔ **THE REFERENCE IS RESOLVED ONCE AND THE ANSWER IS KEPT, BECAUSE
        // *"WE HAVE NO USABLE ID"* AND *"THE SITE SAYS THAT ID IS GONE"* ARE
        // TWO FACTS AND ONLY ONE OF THEM IS A REMOVAL** (6159). Reading a
        // `null` from the lookup as either would put 6040's own shape back
        // inside the fix for it: a reference this class cannot parse asks the
        // site nothing at all, and reporting *"the page we created is no longer
        // on the site"* on the strength of it is a success we did not establish.
        $ref = $this->pageRefParts($set);

        try {
            $byRef = $ref === null ? null : $this->client->locateById($site, $ref[0], $ref[1], ['title']);
        } catch (WordPressRequestFailed $e) {
            return AdapterOutcome::failed('wordpress: '.$e->reason);
        }

        if (is_array($byRef)) {
            [$post, $status] = $byRef;

            if ($status !== 'publish') {
                // ⚠️ **VERIFIED, RATHER THAN INFERRED FROM AN ABSENCE.** We
                // asked the site about **our** post and it says the post is not
                // public — a draft, pending, private, or in the trash. That is
                // the end state this method exists to produce, and it is the one
                // arm where *"nothing of ours is published"* is a fact rather
                // than a guess.
                return AdapterOutcome::ok('wordpress: '.$post->type.'/'.$post->id.' is already not published');
            }

            return $this->takeDown($site, $post);
        }

        try {
            $post = $this->client->locate($site, $set->url, ['title']);
        } catch (WordPressRequestFailed $e) {
            return AdapterOutcome::failed('wordpress: '.$e->reason);
        }

        if ($post !== null) {
            return $this->takeDown($site, $post);
        }

        if ($ref !== null) {
            // ⛔ **BOTH ANSWERS AGREE AND THE ROUTE IS PROVABLY ALIVE.** The
            // collections were queried and both answered — that is
            // {@see WordPressRestClient::locate()}'s contract for returning
            // `null` rather than throwing — so `wp/v2/pages` exists and is
            // serving. A `404` on `wp/v2/pages/<id>` against a live route is
            // therefore a fact about the **post**: the page we created has been
            // permanently deleted, which needs `force=true` and is somebody
            // doing it on purpose. Nothing of ours is on that website.
            return AdapterOutcome::ok('wordpress: the page we created is no longer on the site');
        }

        // ⛔ **THE ARM 6040 WAS ABOUT, AND IT IS NOT A SUCCESS.** Nothing
        // answers at that address and we have no recorded id to ask about, so
        // *"nothing is published at that address"* — which is what this used to
        // return as `ok` — is the one thing we have **not** established. A slug
        // edit, a permalink-structure change, a `www.` change, a reparent or a
        // domain move all look exactly like this from here, and every one of
        // them leaves our page live and indexed under the owner's name.
        return AdapterOutcome::unverified(
            'wordpress: nothing is published at that address, and we cannot tell whether the page moved or was taken down',
        );
    }

    /**
     * Draft one post and say so.
     *
     * ⚠️ **THE REFUSAL IS A RETURN VALUE HERE, NOT A THROW** — `CmsAdapter`'s
     * rule, and the reason this is a method rather than two copies of the same
     * `try`: {@see self::unpublishPage()} reaches the write from two different
     * lookups, and a second copy is a second place the catch could be forgotten.
     */
    private function takeDown(WordPressSite $site, WordPressPost $post): AdapterOutcome
    {
        try {
            $this->client->unpublish($site, $post);
        } catch (WordPressRequestFailed $e) {
            return AdapterOutcome::failed('wordpress: '.$e->reason);
        }

        return AdapterOutcome::ok('wordpress: '.$post->type.'/'.$post->id.' unpublished');
    }

    /**
     * The post type and id this change set's recorded reference names, or
     * `null` if there is no usable one.
     *
     * ⛔ **A `null` HERE MEANS *"WE HAVE NOTHING TO ASK ABOUT"* AND NEVER *"THE
     * SITE DOES NOT HAVE IT"*, AND THE CALLER DEPENDS ON THAT DISTINCTION**
     * (6159). {@see self::unpublishPage()} reads a `404` on the id as *the page
     * was permanently deleted* — but only because the collection query answered,
     * proving the route alive. **A reference this class could not parse asked
     * the site nothing**, so the same conclusion drawn from it would be a
     * removal reported on no evidence, which is 6040 committed by the fix for
     * 6040 (363–365).
     *
     * ⚠️ **A MALFORMED REFERENCE IS A `null` AND NOT A THROW**, because the
     * fallback is the address and a value this class cannot read is exactly as
     * useful as no value at all. A reference naming a post **type** this client
     * does not know is a throw, inside the client, because guessing a REST base
     * is how an unrelated endpoint receives a POST.
     *
     * @return array{string, int}|null
     */
    private function pageRefParts(ChangeSet $set): ?array
    {
        if ($set->pageRef === null) {
            return null;
        }

        $parts = explode('/', $set->pageRef);

        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return [$parts[0], (int) $parts[1]];
    }

    /**
     * Put the page back.
     *
     * ⚠️ **THE SAME WRITE PATH, WITH THE SIDES SWAPPED** — `ChangeSet::inverted()`
     * is what the caller hands over, so a revert is subject to the same read-back
     * as an apply. A rollback that silently did not take would be the worst
     * failure this class has: an owner told their page was restored, looking at
     * a page that was not.
     *
     * ⛔ **AND IT IS NO LONGER LITERALLY `writeChangeSet()`, WHICH IS 6042**
     * (closed at 6142). The two differ by one argument — {@see self::edit()}'s
     * `$expectStored` — and what that argument buys is the difference between
     * putting *our* change back and writing over whatever the owner has written
     * in the fortnight to a month since. **This method's own docblock named this
     * class's worst failure while the method beneath it committed a second
     * one**; both are checks now rather than sentences.
     */
    public function rollback(Location $location, ChangeSet $set): AdapterOutcome
    {
        // ⚠️ **`$set` ARRIVES INVERTED, SO ITS `before` IS WHAT *WE* WROTE.**
        // {@see ChangeSet::inverted()} swaps the sides and `SiteChanges::revert()`
        // is what hands it over, which is why the values to check the page
        // against are on the side a first-time write would be restoring from.
        return $this->edit($location, $set, expectStored: $set->before);
    }

    /**
     * Give the site back.
     *
     * ⚠️ **NO ACTOR REACHES HERE, SO THE ACTOR IS AUTOPILOT.** `CmsAdapter`'s
     * signature takes a `Location` and nothing else, and widening it to carry an
     * actor would change every implementation for one call site. An erasure or a
     * disconnect screen that knows who asked should call
     * {@see WordPressCredentials::forget()} directly with its own actor —
     * which is what slice G's screen will do, and what a tenant-deletion sweep
     * must do.
     */
    public function uninstall(Location $location): AdapterOutcome
    {
        $revoked = $this->credentials->forget($location, ActuationActor::autopilot());

        return match ($revoked) {
            null => AdapterOutcome::ok('wordpress: nothing was connected'),
            true => AdapterOutcome::ok('wordpress: credential revoked at the site'),
            false => AdapterOutcome::failed('wordpress: our copy is gone and the site could not revoke it'),
        };
    }

    /**
     * Write one change set's fields to a page that already exists, optionally
     * refusing if the page is not the one we left.
     *
     * ⛔ **`$expectStored` IS THE WHOLE OF 6042 AND IT IS `null` ON A FIRST
     * WRITE** (closed at 6142). A rollback puts a page's prior values back
     * **fourteen to thirty days** after the write —
     * `SiteMeasurements::MEASURED_WINDOW_ENDS_DAYS` — and an owner who disliked
     * our edit is the *most* likely person to have rewritten that page in the
     * meantime. Before this, nothing compared anything: `write()` sent no
     * `If-Unmodified-Since` and no revision id, and whatever the owner had
     * written was replaced by our snapshot with no record anywhere of what it
     * had said. **The owner screen promises the opposite in as many words** —
     * *"we put the page back the way it was, and we never delete anything you
     * wrote."*
     *
     * ⛔ **THE ANSWER TO A MISMATCH IS TO LEAVE THEIR PAGE ALONE, NOT TO WRITE
     * ANYWAY AND FILE A NOTE.** Rule 32's reversibility is a promise about *our*
     * change, not a licence over the page it is on; and the only recovery from
     * overwriting somebody's own words here is WordPress's post revisions, which
     * is the customer's to find and ours to have caused.
     *
     * ⛔ **AND THE REFUSAL IS `Unverified` RATHER THAN `Failed`.** Nothing was
     * refused by the website and nothing was even asked of it — what happened is
     * that we could not establish that the page in front of us is the page this
     * change set is about, which is the same fact `unpublishPage()` reports for a
     * page that has moved, and it is the one an owner needs told.
     *
     * ⚠️ **THIS CHECK IS THE ADAPTER'S AND NOT THE CHOKEPOINT'S, AND THE REASON
     * IS COST RATHER THAN TIDINESS.** `SiteChanges::revert()` could re-snapshot
     * through `CmsAdapter::snapshot()` and compare there, which would cover every
     * adapter at once — and it would be **a second read of a stranger's server
     * for a fact this method already holds**, on the one path 6055 records as
     * having no politeness budget at all. ⛔ **The consequence is written down
     * rather than assumed away: a second adapter that writes to a real site must
     * carry this check itself**, and a lint in `Architecture\ActuationTest` names
     * the file this one lives in so that a future implementation has to argue
     * with it rather than forget it.
     *
     * @param  array<string, mixed>|null  $expectStored
     */
    private function edit(Location $location, ChangeSet $set, ?array $expectStored): AdapterOutcome
    {
        $site = $this->gatedSite($location);

        if ($site instanceof AdapterOutcome) {
            return $site;
        }

        $fields = $this->writableFields($set->after);

        if ($fields === null) {
            // ⛔ **REFUSED BY NAME RATHER THAN SILENTLY NARROWED** (5532's rule).
            // A change set naming `meta_description` and `title` that quietly
            // wrote only the title would record both in `after_snapshot` and put
            // one of them on the page — so the row, the feed and the measurement
            // window would all describe a change that half happened.
            return AdapterOutcome::failed('wordpress: change type not writable over core REST');
        }

        $expected = $expectStored === null ? null : $this->writableFields($expectStored);

        if ($expectStored !== null && $expected === null) {
            // We cannot say what the page should be holding, so we cannot say
            // whether it still is. The one thing we will not do is write anyway.
            return AdapterOutcome::unverified('wordpress: what we wrote is not readable as a set of fields, so the page was left alone');
        }

        /** @var list<string> $wanted */
        $wanted = array_values(array_unique(array_merge(
            array_keys($fields),
            array_keys($expected ?? []),
        )));

        try {
            $post = $this->pageToWrite($site, $set, $wanted);
        } catch (WordPressRequestFailed $e) {
            return AdapterOutcome::failed('wordpress: '.$e->reason);
        }

        if ($post === null) {
            if ($expected !== null) {
                // ⛔ **6040's ARM ON THE EDIT SIDE.** *"Not found"* is what a
                // moved slug, a changed permalink structure and a deleted page
                // all look like from here, and on a **revert** the difference
                // matters: reporting a failure the owner can retry is honest,
                // and reporting a success would close the row on a page still
                // carrying our change.
                return AdapterOutcome::unverified('wordpress: we could not find the page we changed, so nothing was written');
            }

            // ⚠️ **THE PAGE THIS CHANGE SET IS ABOUT IS NOT THERE.** It was when
            // the snapshot was taken; an owner may have unpublished it in
            // between. Editing is not creating, and this method never creates —
            // {@see self::createPage()} is a different verb and a different
            // change set (5770).
            return AdapterOutcome::failed('wordpress: not_found');
        }

        if ($expected !== null && ! SiteSnapshot::read(array_intersect_key($post->fields, $expected))->matches($expected)) {
            return AdapterOutcome::unverified('wordpress: the page is not as we left it, so what is on it now was not written over');
        }

        try {
            $this->client->write($site, $post, $fields);
        } catch (WordPressRequestFailed $e) {
            if (! in_array($e->reason, self::LANDED_BUT_WRONG, true)) {
                // The write did not land. Nothing to put back.
                return AdapterOutcome::failed('wordpress: '.$e->reason);
            }

            return $this->abort($site, $post, $set, $e->reason);
        }

        return AdapterOutcome::ok(
            'wordpress: '.$post->type.'/'.$post->id.' updated',
            $post->type.'/'.$post->id,
        );
    }

    /**
     * The published page to write to — by the recorded id where there is one,
     * and by the address otherwise.
     *
     * ⚠️ **A REFERENCE THAT RESOLVES TO SOMETHING NOT PUBLISHED IS NOT USED
     * HERE**, unlike in {@see self::unpublishPage()}. An edit's whole subject is
     * a page a visitor can reach; `write()` asserts the page is still public
     * afterwards (5594's refusal to move a status), so writing to a draft would
     * abort and restore for a reason that has nothing to do with the write.
     * Falling back to the address is what the URL arm was already doing.
     *
     * @param  list<string>  $fields
     *
     * @throws WordPressRequestFailed
     */
    private function pageToWrite(WordPressSite $site, ChangeSet $set, array $fields): ?WordPressPost
    {
        $ref = $this->pageRefParts($set);

        $byRef = $ref === null ? null : $this->client->locateById($site, $ref[0], $ref[1], $fields);

        if (is_array($byRef) && $byRef[1] === 'publish') {
            return $byRef[0];
        }

        return $this->client->locate($site, $set->url, $fields);
    }

    /**
     * Undo a write that landed only partly, and say which of the two things
     * happened.
     */
    private function abort(WordPressSite $site, WordPressPost $post, ChangeSet $set, string $reason): AdapterOutcome
    {
        $before = $this->writableFields($set->before);

        if ($before === null) {
            return AdapterOutcome::failed('wordpress: the site changed what we wrote and the snapshot cannot be put back ('.$reason.')');
        }

        try {
            // ⛔ **`restore()`, NOT `write()`, AND THE DIFFERENCE IS ONE
            // ASSERTION** (5981). A page the site has taken out of public view
            // cannot be put back into it from here — writing `status` on an
            // update is refused by name (5594) — so a restore that asserted the
            // page was published would report a content restore that worked as
            // one that failed, on the arm reached precisely when the site has
            // just unpublished it.
            $this->client->restore($site, $post, $before);
        } catch (WordPressRequestFailed $e) {
            // ⛔ **THE ONE OUTCOME IN THIS CLASS THAT NEEDS A PERSON.** The page
            // is not what it was and is not what we asked for, and this platform
            // could not fix it.
            return AdapterOutcome::failed('wordpress: the site changed what we wrote and the page could not be restored ('.$reason.'; '.$e->reason.')');
        }

        return AdapterOutcome::failed('wordpress: the site changed what we wrote, so the page was put back ('.$reason.')');
    }

    /**
     * The site to write with, or the outcome that says why we will not.
     *
     * ⛔ **§19.7's GATE, RE-RUN AGAINST THE LIVE CREDENTIAL ONE REQUEST BEFORE
     * THE WRITE — 5819(c)** (5985). It was checked at connect time and at
     * `health()`, and a WordPress administrator can change a user's role between
     * either of those and this line. `WordPressCredentials::siteForWriting()`
     * carries the argument, the cost and the limit: **the window is narrowed and
     * not closed**, because WordPress offers no check-and-write.
     *
     * ⚠️ **THE THREE ANSWERS STAY THREE.** *Nothing connected* is not a refusal
     * — no site was asked and there is no verdict to record — so it keeps its own
     * sentence rather than arriving as `not_authenticated`, which is what a
     * revoked password says.
     */
    private function gatedSite(Location $location): WordPressSite|AdapterOutcome
    {
        $site = $this->credentials->siteForWriting($location);

        return match (true) {
            $site instanceof WordPressSite => $site,
            $site === null => AdapterOutcome::failed('wordpress: nothing connected'),
            default => AdapterOutcome::failed('wordpress: '.$site->detail()),
        };
    }

    /**
     * The change set's fields, or null if any of them cannot be written.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>|null
     */
    private function writableFields(array $values): ?array
    {
        $fields = [];

        foreach ($values as $name => $value) {
            if (! array_key_exists($name, WordPressRestClient::WRITABLE_FIELDS) || ! is_string($value)) {
                return null;
            }

            $fields[$name] = $value;
        }

        return $fields === [] ? null : $fields;
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function supported(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            static fn (string $field): bool => array_key_exists($field, WordPressRestClient::WRITABLE_FIELDS),
        ));
    }
}
