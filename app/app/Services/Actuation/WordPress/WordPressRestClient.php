<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

use App\Enums\OutboundSiteRefusal;
use App\Exceptions\WordPressRequestFailed;
use App\Services\Actuation\SiteSnapshot;
use App\Services\Config\DefaultsRegistry;
use App\Support\OutboundSiteBudget;
use App\Support\PublicAddress;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * WordPress core's own REST API, over an owner-pasted Application Password.
 *
 * ⛔ **NO PLUGIN OF OURS IS INVOLVED, AND THAT IS THE WHOLE OF `BUILD-PLAN`
 * §2.11.5 CONFLICT 7.** WordPress's authentication handbook: *"As of 5.6,
 * WordPress has shipped with Application Passwords"*, and *"The credentials can
 * be passed along to REST API requests served over https:// using Basic Auth /
 * RFC 7617"* (`developer.wordpress.org/rest-api/using-the-rest-api/authentication/`,
 * page last updated 2025-06-04, fetched 2026-08-19). Endpoints, arguments and
 * error shapes below were read from the same handbook and from core's own source
 * on the same day, never from memory.
 *
 * ## What this client can and cannot reach, verified rather than assumed
 *
 * ✅ **Reads and writes `title`, `content` and `excerpt` on a published post or
 * page**, in `edit` context, which is where core exposes the `raw` values a
 * rollback needs (`WP_REST_Posts_Controller::get_item_schema` — `raw` carries
 * `'context' => array( 'edit' )` on all three, checked 2026-08-19).
 *
 * ⛔ **IT CANNOT WRITE A META DESCRIPTION, AND THE PLAN ASSUMES IT CAN.**
 * `site_changes`' own migration names `meta_description` as the first change
 * type. **WordPress core has no meta description**: it is a plugin's registered
 * post meta, and core only exposes registered meta over REST when the plugin
 * passed `show_in_rest` — *"By setting a meta field's `show_in_rest` parameter
 * to `true`, that field's value will be exposed on a `.meta` key"*
 * (`developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/`,
 * fetched 2026-08-19), which is per-plugin and per-site. So the field is refused
 * by name here rather than silently dropped, and it is owed to a later slice
 * that has read Yoast's and Rank Math's own REST surfaces.
 *
 * ⚠️ **AND THE PUBLISHING PIPELINE NOW ASKS BEFORE IT BUILDS A CHANGE SET —
 * 2026-08-20 (5772).** Until today `Publishing` named `meta_description` in
 * every set it made, so this refusal fired on **every** live publish (5753(a));
 * `WordPressAdapter::fieldSupport()` is where that question is answered, and the
 * field it drops is recorded on `site_changes.withheld_fields` with the reason
 * rather than quietly left out. **The refusal itself is unchanged**: a change
 * set that still names the field is still refused whole.
 *
 * ## What it can create, which is narrower than what it can edit
 *
 * ⛔ **IT CREATES A `page`, AT A SINGLE-SEGMENT ADDRESS, AND PROVES THE
 * PERMALINK** (5773). A growth page is a page that does not exist yet, which is
 * decision 5753's sharper half. Core will happily create one and put it at an
 * address of its own choosing — `wp_unique_post_slug()` appends `-2` to a taken
 * slug, and a `post` follows whatever the permalink structure says — so
 * {@see self::create()} compares the created page's `link` against the URL the
 * change set names and **unpublishes its own creation** when they differ. A
 * nested address needs a parent id this platform would have to guess at and is
 * refused by name.
 *
 * ⛔ **IT CANNOT ADDRESS THE FRONT PAGE.** Core REST offers no permalink lookup,
 * so a URL is resolved through its last path segment as a slug — and the site
 * root has no segment. The obvious fix, reading `page_on_front` from
 * `/wp/v2/settings`, needs `manage_options`, **which §19.7's gate forbids this
 * credential from having**. The two requirements genuinely collide; the front
 * page is refused with a reason and left owed.
 *
 * ⛔ **IT CANNOT CHANGE A SLUG OR A STATUS**, and that is a narrowing rather
 * than a limit. Both are writable over core REST. Changing a slug changes the
 * page's URL and breaks every link to it; changing a status unpublishes it. An
 * SEO automation that can silently take a business's page off the internet is
 * not a smaller version of one that cannot — `CLAUDE.md`'s first tie-breaker is
 * less support surface, and this is the largest one on offer.
 *
 * ## The read-back is not paranoia, it is the one silent failure this API has
 *
 * ⛔ **A WRITE CAN RETURN `200` AND NOT HAVE HAPPENED.** `kses_init()` in core
 * reads, in full: *"`kses_remove_filters(); if ( ! current_user_can(
 * 'unfiltered_html' ) ) { kses_init_filters(); }`"* — so a user **without**
 * `unfiltered_html` has disallowed markup stripped out of their content on save,
 * and the REST API answers `200` with the sanitised value. An Editor has that
 * capability on a single-site install and **does not have it on multisite**
 * (`wordpress.org/documentation/article/roles-and-capabilities/`, last updated
 * 2024-09-20), which is precisely the role §19.7's gate asks the owner to
 * create. A caching or optimisation plugin filtering `content_save_pre` does the
 * same thing on any install.
 *
 * So every write is read back and a value that did not take is a **failure**,
 * not a success. Without this, `site_changes.applied_at` would be stamped on a
 * change that never reached the page, and decision 5527 is entirely about that
 * column meaning what it says.
 *
 * ⛔ **AND THE UPDATE RESPONSE IS NOT THE PAGE — READING IT AS THE PAGE IS THE
 * PERMISSIVE HALF DECISION 5819(b) NAMED, AND CORE's OWN SOURCE IS WHERE IT
 * SHOWS** (5980). 5588 read the response back because *"core's `update_item`
 * calls `$request->set_param( 'context', 'edit' )`, so the `raw` values come
 * back without a second request"*. **The `context` half is true and the
 * conclusion does not follow.** `WP_REST_Posts_Controller::update_item()` ends,
 * verbatim:
 *
 * ```
 * $post          = get_post( $post_id );
 * $fields_update = $this->update_additional_fields_for_object( $post, $request );
 * …
 * $request->set_param( 'context', 'edit' );
 * …
 * do_action( "rest_after_insert_{$this->post_type}", $post, $request, false );
 * wp_after_insert_post( $post, true, $post_before );
 * $response = $this->prepare_item_for_response( $post, $request );
 * ```
 *
 * (`developer.wordpress.org/reference/classes/wp_rest_posts_controller/update_item/`,
 * source shown for WordPress **7.1**, fetched 2026-08-20.) **The `$post` handed
 * to `prepare_item_for_response()` was fetched before those two hooks ran.** A
 * plugin that rewrites the post inside `wp_after_insert_post` — *"Fires once a
 * post, its terms and meta data has been saved"*, since 5.6 — or inside
 * `rest_after_insert_{$post_type}` — *"Fires after a single post is completely
 * created or updated via the REST API"*, since 5.0 — writes to the database and
 * **the response still carries the pre-hook content**. So the response proves
 * what `wp_update_post()` stored and says nothing about what the row holds when
 * the request ends: a table-of-contents injector, a link rewriter, a
 * lazy-loading filter and an AI-summary plugin all sit in exactly that window.
 *
 * ⛔ **THEREFORE EVERY WRITE IS FOLLOWED BY A SECOND REQUEST** —
 * `GET /wp/v2/<type>/<id>?context=edit`, {@see self::reread()} — and **both**
 * comparisons are kept, because they are evidence of different things and a
 * person reading a failure needs to know which: a mismatch in the response is
 * filtering **on save** (`content_filtered`), and a mismatch that appears only
 * in the re-read is a rewrite **after** it (`content_rewritten`).
 *
 * ⚠️ **AND THE RE-READ IS STILL NOT THE PAGE A VISITOR SEES.** It is the `raw`
 * value stored in `wp_posts`. What a visitor gets is that string after
 * `the_content` — shortcodes, `wpautop`, oEmbed, block rendering — and after
 * whatever page cache sits in front of WordPress. **Nothing in this class reads
 * that**, and reading it would be an anonymous HTML fetch, which `40` Part 8
 * puts behind `FetchGateway`. The honest scope of this client's claim is *"the
 * row WordPress stores says what we asked for"*, and no wider sentence about it
 * may be written here.
 *
 * ⚠️ **AND THE COMPARISON IS THE ONE THING NO TEST HERE CAN SETTLE — NOW AT TWO
 * PLACES RATHER THAN ONE.** If WordPress normalises content in some way this
 * comparison does not model, every legitimate write reports failure and restores
 * itself — an automation that always fails, on every tenant (5590). **The
 * re-read doubles that surface**, because a site that rewrites content on every
 * save legitimately — a link shortener, a CDN image rewriter — now fails every
 * write where before it passed silently and wrongly. **Failing is the correct
 * direction and it is not a free one**: a real install is what settles it, and
 * staging items 7, 19 and 20 carry it.
 *
 * ## What is deliberately absent
 *
 * ⚠️ **NO SYNCHRONOUS RETRY.** `ZernioGbpClient`'s rule: one attempt, then a
 * classified failure, and retrying belongs to the job that has backoff and
 * jitter.
 *
 * ⛔ **THIS PARAGRAPH NAMED `WordPressRequestFailed::$retryable` UNTIL
 * 2026-08-20, AND THAT PROPERTY DOES NOT EXIST — CORRECTED (6260).** The class
 * it points at says so itself, in bold, in the docblock of the thing it
 * replaced: the flag *"was written and removed inside this slice"* because
 * nothing in `app/` would have read it. **The vocabulary is
 * {@see WordPressRequestFailed::$reason}**, whose transient labels are
 * `unreachable`, `unavailable` and — since 6261 — `throttled`, which since
 * 9800–9819 carries {@see WordPressRequestFailed::$brake} beside it, because
 * *"we did not ask"* is two facts and only one of them is about the site.
 *
 * ⛔ **AND THE SENTENCE WAS WRONG A SECOND TIME, ABOUT THE READER RATHER THAN
 * THE FIELD.** No job reads either one. This exception is caught by
 * {@see WordPressAdapter} and turned into
 * an `AdapterOutcome` — `CmsAdapter`'s *"a refusal is a return value at the
 * boundary"* rule — and `UndoSiteChangeJob` says in its own docblock that **an
 * adapter refusal is not an exception and does not retry**. So the three
 * actuation jobs' `$tries` and `backoff()` cover a *thrown* failure, and what
 * carries a refused write forward is the nightly sweep, now bounded by
 * `SiteMeasurements::REVERT_ATTEMPT_CEILING`. The thing that actually honours a
 * `429` is {@see OutboundSiteBudget}, which needs no job at all.
 *
 * ⚠️ **NO REDIRECT FOLLOWING ON THE AUTHENTICATED CALLS.** A `301` off a
 * tenant's site is a Basic Auth header — a working login — being handed to
 * whatever the redirect names. Guzzle strips `Authorization` on a **cross-host**
 * redirect and keeps it on a same-host one, and "same host" is decided by the
 * site rather than by us. The discovery probe follows redirects because it
 * carries no credential and `https://example.com` → `https://www.example.com` is
 * the single commonest thing an owner pastes.
 */
final class WordPressRestClient
{
    public function __construct(private readonly DefaultsRegistry $registry) {}

    /**
     * The fields a change set may name, and the core REST arguments they are.
     *
     * ⚠️ **THE KEYS ARE OURS AND THE VALUES ARE WORDPRESS's**, and today they
     * happen to be the same three words. The map exists so that the day they
     * stop being the same — `meta_description` is the obvious next one — there
     * is a place for the translation that is not an `if` inside a loop.
     *
     * @var array<string, string>
     */
    public const array WRITABLE_FIELDS = [
        'title' => 'title',
        'content' => 'content',
        'excerpt' => 'excerpt',
    ];

    /**
     * The post types a URL is looked for in, most likely first.
     *
     * ⚠️ **PAGES BEFORE POSTS IS NOT ARBITRARY.** The pages this platform edits
     * are service and location pages; a blog post sharing a slug with one of
     * them is the collision this order resolves in the less surprising
     * direction. A match in both is refused rather than resolved.
     *
     * @var list<string>
     */
    private const array POST_TYPES = ['pages', 'posts'];

    private const int CONNECT_TIMEOUT_SECONDS = 5;

    public const int TIMEOUT_SECONDS = 15;

    /**
     * Find the REST root of a site, or fail.
     *
     * ⚠️ **TWO SHAPES, BOTH DOCUMENTED, AND THE SECOND IS NOT AN EDGE CASE.**
     * WordPress's discovery page gives `https://example.com/wp-json/` for a site
     * with pretty permalinks and `https://example.com/?rest_route=/` for one
     * without, and tells clients to *"ensure that both routes can be handled
     * seamlessly"*. A small business on a default install is exactly the
     * population with plain permalinks.
     *
     * ⚠️ **THE `Link` HEADER IS THE VENDOR'S OWN PREFERRED METHOD AND IS
     * DELIBERATELY NOT USED HERE.** It lives on the site's front page, so
     * reading it is an HTML fetch — and `40` Part 8 puts every HTML fetch behind
     * `FetchGateway`, where robots, the method ceiling and the attempt ledger
     * live. `BUILD-PLAN` §2.11.3 gives that probe to **slice B**. This asks the
     * two documented API roots directly, which is a JSON API call like every
     * other entry on the outbound allowlist. **The seam is deliberate**: when
     * B's gateway-side detection lands, it supplies the header's answer and this
     * becomes the fallback rather than the only route.
     *
     * @throws WordPressRequestFailed
     */
    public function discover(string $siteUrl): string
    {
        $siteUrl = rtrim($siteUrl, '/');

        $this->assertReachableAddress($siteUrl);

        foreach ([$siteUrl.'/wp-json/', $siteUrl.'/?rest_route=/'] as $candidate) {
            $response = $this->attempt('GET', $candidate, fn (): Response => $this->anonymous()->get($candidate));

            if (! $response->successful()) {
                continue;
            }

            $namespaces = $response->json('namespaces');

            if (! is_array($namespaces)) {
                continue;
            }

            // ⚠️ **`wp/v2` IS CHECKED RATHER THAN ASSUMED.** WordPress's own
            // discovery page: *"WordPress 4.4 enabled the API infrastructure for
            // all sites, but did **not** include the core endpoints"* — and a
            // security plugin removing the namespace is ordinary hardening.
            if (! in_array('wp/v2', $namespaces, true)) {
                throw WordPressRequestFailed::unreadable('no_core_endpoints');
            }

            return $candidate;
        }

        throw WordPressRequestFailed::unreachable();
    }

    /**
     * Who this credential belongs to, and what it can do.
     *
     * @throws WordPressRequestFailed
     */
    public function identify(WordPressSite $site): WordPressIdentity
    {
        $url = $site->route('wp/v2/users/me', ['context' => 'edit']);

        $response = $this->authenticated($site, 'GET', $url);

        $userId = $response->json('id');
        $roles = $response->json('roles');
        $capabilities = $response->json('capabilities');

        if (! is_int($userId) || ! is_array($roles) || ! is_array($capabilities)) {
            throw WordPressRequestFailed::unreadable('identity');
        }

        return new WordPressIdentity(
            userId: $userId,
            roles: array_values(array_map(
                static fn (mixed $role): string => is_string($role) ? $role : '',
                $roles,
            )),
            capabilities: $capabilities,
        );
    }

    /**
     * §19.7's behavioural arm: can this credential see the plugins it would
     * install?
     *
     * ⛔ **`null` MEANS "COULD NOT ASK", AND IT IS NOT A PASS OR A FAIL.** Core's
     * plugins controller refuses with `rest_cannot_view_plugins` and a status
     * from `rest_authorization_required_code()` — `403` for a logged-in user —
     * so a refusal is a clean *no*. Anything else (a `404` from a site that
     * removed the endpoint, a `5xx`) is an absence of evidence, and
     * {@see LeastPrivilege} refuses only on evidence of power.
     */
    public function canManagePlugins(WordPressSite $site): ?bool
    {
        $url = $site->route('wp/v2/plugins', ['context' => 'view', 'per_page' => 1]);

        try {
            $this->authenticated($site, 'GET', $url);
        } catch (WordPressRequestFailed $e) {
            // ⚠️ **ONLY A `403` IS A CLEAN NO.** A `401` means the credential
            // was not accepted at all, which is a different answer to a
            // different question — and it cannot reach here in practice, because
            // {@see WordPressCredentials} identifies before it probes.
            return $e->reason === 'forbidden' ? false : null;
        }

        return true;
    }

    /**
     * Revoke the exact Application Password this platform is holding, and
     * nothing else.
     *
     * ⛔ **THIS IS 4880's OBLIGATION WITH A REAL CODE PATH, AND IT ALMOST WAS
     * NOT.** A customer who leaves and leaves us holding write access to their
     * website is the Zernio grant nothing revoked, wearing different clothes —
     * and the first draft of this slice recorded it as impossible, because
     * revoking a password needs its **UUID** and WordPress only returns one when
     * the password is *created* through its authorisation flow. An owner who
     * generated theirs by hand in wp-admin has a UUID we never saw.
     *
     * ✅ **Core answers it anyway, through a route the plan never mentions.**
     * `GET /wp/v2/users/me/application-passwords/introspect` returns *"the
     * application password being currently used for authentication"* — so a
     * request made **with** the credential can ask the site which credential it
     * is. Core's own source:
     * `$uuid = rest_get_authenticated_app_password(); if ( ! $uuid ) return new
     * WP_Error( 'rest_no_authenticated_app_password', …, array( 'status' => 404 ) );`
     * (`WP_REST_Application_Passwords_Controller::get_current_item`, read
     * 2026-08-19). The UUID then goes to `DELETE
     * /wp/v2/users/me/application-passwords/<uuid>`.
     *
     * ⛔ **THE `<uuid>` IS WHAT MAKES THIS SAFE TO CALL.** Core also offers
     * `DELETE /wp/v2/users/<id>/application-passwords` with no UUID, which
     * revokes **every** application password that user has — including ones
     * belonging to their backup plugin and their accountant. That route is
     * deliberately never reached from this codebase.
     *
     * @return bool True when the site confirmed the revocation. False means the
     *              site could not tell us which password we are — an older
     *              WordPress, or a site with application passwords disabled —
     *              and the owner has to remove it by hand.
     *
     * @throws WordPressRequestFailed
     */
    public function revokeOwnCredential(WordPressSite $site): bool
    {
        try {
            $introspection = $this->authenticated(
                $site,
                'GET',
                $site->route('wp/v2/users/me/application-passwords/introspect'),
            );
        } catch (WordPressRequestFailed $e) {
            // A 404 here is core's own `rest_no_authenticated_app_password`: the
            // request authenticated some other way, or the feature is off. It is
            // an answer, not a transport failure.
            if ($e->reason === 'not_found') {
                return false;
            }

            throw $e;
        }

        $uuid = $introspection->json('uuid');

        if (! is_string($uuid) || $uuid === '') {
            return false;
        }

        $this->authenticated(
            $site,
            'DELETE',
            $site->route('wp/v2/users/me/application-passwords/'.rawurlencode($uuid)),
        );

        return true;
    }

    /**
     * The post or page a URL names, with its current `raw` field values — or
     * `null` if the site has no published page at that address.
     *
     * ⛔ **`null` IS AN OBSERVATION AND A `404` IS A FAILURE, AND CONFLATING
     * THEM CREATES A PAGE ON A SITE WE COULD NOT READ** (5770). This returned a
     * `WordPressPost` or threw `notFound()`, and `notFound()` is *also* what
     * {@see self::classified()} throws for an HTTP `404` — a REST root that has
     * stopped answering, an endpoint a security plugin removed, a site that is
     * no longer WordPress. Decision 5753's fix makes *"we looked and nothing is
     * published here"* the state a **creation** proceeds from, so the two
     * answers can no longer share a channel: absence is a return value, and
     * everything else stays an exception.
     *
     * ⚠️ **THE COLLECTIONS MUST HAVE ANSWERED FOR THIS TO BE `null`.** Both
     * queries are made and both must succeed; a transport failure on either one
     * throws before this can return.
     *
     * @param  list<string>  $fields
     *
     * @throws WordPressRequestFailed
     */
    public function locate(WordPressSite $site, string $url, array $fields): ?WordPressPost
    {
        $slug = $this->slugOf($url);

        $found = [];

        foreach (self::POST_TYPES as $type) {
            $match = $this->firstMatching($site, $type, $slug, $url, $fields);

            if ($match !== null) {
                $found[] = $match;
            }
        }

        if ($found === []) {
            return null;
        }

        if (count($found) > 1) {
            // A page and a post sharing a slug and both claiming the URL. There
            // is no correct guess, and guessing edits a stranger's website.
            throw WordPressRequestFailed::unreadable('ambiguous_url');
        }

        return $found[0];
    }

    /**
     * The post an adapter's own recorded reference names, with its current
     * status — or `null` if the site says no such post exists.
     *
     * ⛔ **THIS IS WHAT MAKES A MOVED PAGE FINDABLE, AND {@see self::locate()}
     * IS WHAT MAKES IT UNFINDABLE** (6040, closed at 6141). `locate()` resolves
     * a URL through its last path segment and then compares the candidate's
     * `link` against the URL exactly apart from scheme and trailing slash — so
     * an owner editing a slug, changing the permalink structure, adding or
     * dropping `www.`, reparenting the page or moving domain makes it answer
     * `null` **while our page is still published**. The post id does not move.
     *
     * ⛔ **`null` IS A NARROW ANSWER AND IS NOT *"THE PAGE IS GONE"*.** Core
     * answers `404` for a post id it does not recognise **and** for a route it
     * does not have, and `WordPressRequestFailed::notFound()` is what both
     * arrive as — the exact conflation 5770 separated on the other side. So this
     * says only *"the site answered 404 for that id"*, and the caller is what
     * decides what that is worth. {@see WordPressAdapter::unpublishPage()} does
     * it by asking `locate()` afterwards: a collection query that **answers**
     * proves the route is alive, which is what turns a `404` here into a fact
     * about the post rather than about the endpoint — and it costs no extra
     * request, because that call was going to be made anyway.
     *
     * ⚠️ **`context=edit` AND EVERY STATUS**, unlike `locate()`. A page we are
     * about to take down may already be a draft, and a lookup that only saw
     * published posts could not tell *"already unpublished"* from *"not there"*
     * — which is the whole distinction this method exists to make.
     *
     * @param  list<string>  $fields
     * @return array{WordPressPost, string}|null
     *
     * @throws WordPressRequestFailed
     */
    public function locateById(WordPressSite $site, string $type, int $id, array $fields): ?array
    {
        if (! in_array($type, self::POST_TYPES, true)) {
            // A reference this client did not write, or one from a build that
            // knew a post type this one does not. Guessing a REST base is how
            // an unrelated endpoint gets a POST — {@see self::typeOf()}.
            throw WordPressRequestFailed::unreadable('unknown_post_type');
        }

        try {
            return $this->reread($site, $type, $id, $fields);
        } catch (WordPressRequestFailed $e) {
            if ($e->reason === 'not_found') {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Create a published page at the address a change set names, and prove it
     * landed there.
     *
     * ⛔ **THE CREATED PAGE MUST ANSWER ON THE URL WE ASKED FOR, OR IT IS TAKEN
     * BACK OFF THE SITE** (5773). WordPress decides a permalink from the slug,
     * the post type, the permalink structure and any page with that slug already
     * — `wp_unique_post_slug()` silently appends `-2` — and none of those is
     * knowable from here. The URL in the change set is what was snapshotted,
     * what a rollback addresses, what measurement reads and what was announced
     * to six search engines, so a page at a *different* address is not a
     * partially good outcome: it is a stray page on somebody's website. It is
     * unpublished again and the creation is reported as a failure.
     *
     * ⛔ **PAGES ONLY, AND A SINGLE PATH SEGMENT ONLY.** A `post` lands wherever
     * the site's permalink structure puts it (`/2026/08/…`), and a nested page
     * address needs a `parent` this platform would have to resolve by guessing
     * which page owns the segment above. Both are refused by name rather than
     * attempted — {@see self::slugOf()}'s reasoning, and 5532's rule about an
     * unbuilt case never silently becoming a different one.
     *
     * ⛔ **`status: publish` AND `slug` ARE WRITTEN HERE AND REFUSED ON AN
     * UPDATE** (5594), which is not an inconsistency. On an existing page, a
     * slug change breaks every link to it and a status change takes a business's
     * page off the internet. On a creation there is no page to break and no
     * audience to lose: the slug is what puts the page at the address we
     * snapshotted, and the status is what makes it exist at all.
     *
     * ⚠️ **THE COMPENSATING UNPUBLISH LIVES HERE RATHER THAN IN THE ADAPTER**,
     * which is the other way round from {@see WordPressAdapter}'s `abort()` —
     * and deliberately: an aborted *write* has to put back values only the
     * change set holds, while an aborted *creation* needs nothing but the post
     * that was just made, which never leaves this method.
     *
     * @param  array<string, string>  $fields  Already validated by
     *                                         {@see self::WRITABLE_FIELDS}.
     *
     * @throws WordPressRequestFailed
     */
    public function create(WordPressSite $site, string $url, array $fields): WordPressPost
    {
        $slug = $this->creatableSlugOf($url);

        $payload = $this->payloadFor($fields);

        $payload['slug'] = $slug;
        $payload['status'] = 'publish';

        $response = $this->authenticated($site, 'POST', $site->route('wp/v2/pages'), $payload);

        $created = $this->postFromPayload($response->json(), array_keys($fields), 'pages');

        if ($created === null) {
            // ⛔ **THE ONE OUTCOME HERE THAT MAY HAVE LEFT A PAGE BEHIND.** The
            // request was accepted and the answer was unreadable, so there is no
            // id to unpublish. Named so that the label itself says a person may
            // have to look.
            throw WordPressRequestFailed::unreadable('create_response_page_may_exist');
        }

        if (! $this->sameUrl($created->link, $url)) {
            throw WordPressRequestFailed::unreadable(
                $this->retract($site, $created) ? 'created_at_another_address' : 'created_at_another_address_and_left_published',
            );
        }

        foreach ($fields as $name => $value) {
            if (! $this->matches($created->fields[$name] ?? '', $value)) {
                // The same `kses` filtering an update meets, on a page that did
                // not exist a second ago — so the abort is to take it back down.
                throw WordPressRequestFailed::unreadable(
                    $this->retract($site, $created) ? 'created_content_filtered' : 'created_content_filtered_and_left_published',
                );
            }
        }

        // ⛔ **AND THEN THE PAGE IS READ BACK, BECAUSE EVERYTHING ABOVE IS THE
        // CREATE RESPONSE AND THE CREATE RESPONSE IS NOT THE PAGE** (5980).
        // `WP_REST_Posts_Controller::create_item()` ends the same way
        // `update_item()` does — the object it answers with was fetched before
        // `rest_after_insert_*` and `wp_after_insert_post` ran — so a plugin
        // that rewrites content, re-slugs the page or holds it for review is
        // invisible in it. **All three matter more on a creation than on an
        // edit**: the address is what was announced to six search engines, the
        // content is what nobody has ever seen before, and a page held at
        // `pending` is a creation this platform would otherwise record as live.
        try {
            [$stored, $status] = $this->reread($site, 'pages', $created->id, array_keys($fields));
        } catch (WordPressRequestFailed) {
            throw WordPressRequestFailed::unreadable(
                $this->retract($site, $created) ? 'create_unconfirmed' : 'create_unconfirmed_and_left_published',
            );
        }

        if (! $this->sameUrl($stored->link, $url)) {
            throw WordPressRequestFailed::unreadable(
                $this->retract($site, $stored) ? 'created_page_moved' : 'created_page_moved_and_left_published',
            );
        }

        foreach ($fields as $name => $value) {
            if (! $this->matches($stored->fields[$name] ?? '', $value)) {
                throw WordPressRequestFailed::unreadable(
                    $this->retract($site, $stored) ? 'created_content_rewritten' : 'created_content_rewritten_and_left_published',
                );
            }
        }

        if ($status !== 'publish') {
            // ⚠️ **ONE LABEL RATHER THAN TWO, AND THAT IS NOT AN OVERSIGHT.**
            // The `_and_left_published` suffix exists to say whether a visitor
            // can still see a page we made; here they cannot, whichever way the
            // retraction went. It is still attempted, so that a page sitting at
            // `pending` cannot be approved into existence by somebody who never
            // asked for it.
            $this->retract($site, $stored);

            throw WordPressRequestFailed::unreadable('created_not_published');
        }

        return $stored;
    }

    /**
     * Take a page off the public site, leaving it in the owner's own WordPress.
     *
     * ⛔ **`status: draft`, AND THERE IS NO `DELETE` ANYWHERE ON THIS PATH.**
     * This platform never deletes anything on a customer's website (5771):
     * core's `DELETE /wp/v2/pages/<id>` trashes the page and a second call with
     * `force` destroys it, and neither is ever reached from this codebase. A
     * draft is invisible to every visitor — which is the public state a revert
     * has to restore — and is still there under the owner's own account for them
     * to read, edit or publish themselves.
     *
     * ⚠️ **READ BACK, ON THE SAME RULE AS EVERY OTHER WRITE HERE.** A `200` is
     * not proof: a plugin can filter `wp_insert_post_data` and put the status
     * back, and a revert that silently did not take is the worst failure this
     * class has — an owner told a page is gone, looking at a page that is not.
     *
     * ⛔ **AND THE READ-BACK IS A SECOND REQUEST HERE TOO, FOR THE SAME REASON
     * AS A WRITE** (5980). The update response is built from a `$post` core
     * captured before `rest_after_insert_*` and `wp_after_insert_post` ran, so a
     * workflow plugin that re-publishes on save produces a response saying
     * `draft` and a page that is still on the internet. **This is the path where
     * that matters most**: it is the revert, and its failure sentence is the one
     * an owner is told about a page they asked to have taken down.
     *
     * ⚠️ **`trash` IS DELIBERATELY NOT A FAILURE, AND IT LOOKS LIKE IT SHOULD
     * BE** (5983). If a plugin trashes the post rather than drafting it, 5771's
     * *"the words stay in the owner's own WordPress"* is weakened — trash is
     * recoverable and then it is not. **But what this method promises is the
     * public state**, a trashed page satisfies it, and failing here would leave
     * a `site_changes` row claiming a page is live that is not — which is the
     * contract's own reason for treating an already-absent page as a success.
     * Refused with the argument written down rather than left unnoticed; there
     * is no channel that would carry the observation anywhere useful today.
     *
     * @throws WordPressRequestFailed
     */
    public function unpublish(WordPressSite $site, WordPressPost $post): void
    {
        $response = $this->authenticated(
            $site,
            'POST',
            $site->route('wp/v2/'.$post->type.'/'.$post->id),
            ['status' => 'draft'],
        );

        $status = $response->json('status');

        if (! is_string($status)) {
            throw WordPressRequestFailed::unreadable('unpublish_response');
        }

        if ($status === 'publish') {
            throw WordPressRequestFailed::unreadable('still_published');
        }

        // ⚠️ **`title` IS ASKED FOR BECAUSE THE PARSER NEEDS A FIELD AND EVERY
        // POST HAS ONE IN `edit` CONTEXT**, exactly as `WordPressAdapter`'s own
        // `unpublishPage()` does. Nothing is compared against it; what is being
        // read is the status.
        [, $stored] = $this->reread($site, $post->type, $post->id, ['title']);

        if ($stored === 'publish') {
            throw WordPressRequestFailed::unreadable('republished_by_site');
        }
    }

    /**
     * Write a change set's fields, and prove they landed on the stored page.
     *
     * ⛔ **THE RETURN IS THE RE-READ, NOT THE UPDATE RESPONSE.** See the class
     * docblock: core answers an update from a `$post` it captured *before*
     * `rest_after_insert_*` and `wp_after_insert_post` run, so the response is
     * evidence about the save and not about the row.
     *
     * ⛔ **AND THE PAGE MUST STILL BE PUBLISHED AFTERWARDS.** {@see self::locate()}
     * only ever resolves a `status=publish` post, so the page *was* public when
     * this was called; a moderation or workflow plugin dropping it to `pending`
     * on an Editor's edit takes a business's page off the internet, which is the
     * one outcome 5594 refuses to cause deliberately and must not cause by
     * accident either. {@see self::restore()} is the arm that tolerates it,
     * because a compensating write must not report the content it did put back
     * as a failure.
     *
     * @param  array<string, string>  $fields  Already validated by
     *                                         {@see self::WRITABLE_FIELDS}.
     *
     * @throws WordPressRequestFailed
     */
    public function write(WordPressSite $site, WordPressPost $post, array $fields): WordPressPost
    {
        return $this->send($site, $post, $fields, requirePublic: true);
    }

    /**
     * Put a page's previous values back, on the abort path.
     *
     * ⛔ **THE SAME WRITE AND THE SAME READ-BACK, WITHOUT THE PUBLIC-STATUS
     * ASSERTION, AND THE OMISSION IS THE POINT RATHER THAN A RELAXATION** (5981).
     * This is reached when the site has already done something to the page —
     * including, sometimes, taking it out of public view. **This adapter cannot
     * put a page back into public view**: writing `status` on an update is
     * refused by name (5594), and it is refused because an SEO automation that
     * can move a page's status is one that can take a business off the internet.
     * So asserting the status here would report a content restore that **worked**
     * as one that failed, and the owner's record would say *"your page is
     * neither what it was nor what we asked for"* about a page whose words are
     * exactly what they were.
     *
     * ⚠️ **THE CONTENT COMPARISON IS NOT RELAXED**, and that is the half that
     * matters: a restore that silently did not take is the failure this whole
     * class exists to make impossible.
     *
     * @param  array<string, string>  $fields  Already validated by
     *                                         {@see self::WRITABLE_FIELDS}.
     *
     * @throws WordPressRequestFailed
     */
    public function restore(WordPressSite $site, WordPressPost $post, array $fields): WordPressPost
    {
        return $this->send($site, $post, $fields, requirePublic: false);
    }

    /**
     * @param  array<string, string>  $fields
     *
     * @throws WordPressRequestFailed
     */
    private function send(WordPressSite $site, WordPressPost $post, array $fields, bool $requirePublic): WordPressPost
    {
        $payload = $this->payloadFor($fields);

        $url = $site->route('wp/v2/'.$post->type.'/'.$post->id);

        $response = $this->authenticated($site, 'POST', $url, $payload);

        $written = $this->postFromPayload($response->json(), array_keys($fields));

        if ($written === null) {
            throw WordPressRequestFailed::unreadable('write_response');
        }

        // ⚠️ **FIRST COMPARISON: WHAT `wp_update_post()` STORED.** A credential
        // without `unfiltered_html` has disallowed markup stripped here and gets
        // a `200` with the sanitised value (5588).
        $this->assertTook($written, $fields, 'content_filtered');

        [$stored, $status] = $this->reread($site, $post->type, $post->id, array_keys($fields));

        // ⚠️ **SECOND COMPARISON: WHAT THE ROW HOLDS ONCE THE REQUEST HAS
        // FINISHED.** Everything between the two is a hook core fires after it
        // captured the object it answered with.
        $this->assertTook($stored, $fields, 'content_rewritten');

        if ($requirePublic && $status !== 'publish') {
            throw WordPressRequestFailed::unreadable('unpublished_by_site');
        }

        return $stored;
    }

    /**
     * Read a post back off the site by id, and say what state it is in.
     *
     * ⛔ **BY ID, NEVER BY SLUG.** {@see self::locate()} resolves a URL through
     * its last path segment and refuses an ambiguous match; re-running it here
     * would put the whole of 5593's ambiguity between us and the answer, and the
     * id is a fact we already hold about the page we just wrote to.
     *
     * ⚠️ **`_fields` IS DELIBERATELY NOT USED, AND IT WOULD BE THE CHEAPER
     * CALL** (5982). WordPress documents it — *"To instruct WordPress to return
     * only a subset of the fields in a response, you may use the `_fields` query
     * parameter"*, with nested properties supported since 5.3
     * (`developer.wordpress.org/rest-api/using-the-rest-api/global-parameters/`,
     * last updated 2024-08-08, fetched 2026-08-20) — and `_fields=title.raw`
     * would skip `apply_filters( 'the_content', … )` on the customer's own
     * server, which is the expensive half of preparing this response. **It is
     * refused because the failure mode is wrong**: a site where `_fields` does
     * not behave as documented returns a payload this parser cannot read, every
     * write aborts and restores itself, and 5590's *"an automation that always
     * fails, on every tenant"* arrives through the optimisation rather than
     * through the comparison. Available if the cost ever bites, and then with a
     * staging item of its own.
     *
     * @param  list<string>  $fields
     * @return array{WordPressPost, string}
     *
     * @throws WordPressRequestFailed
     */
    private function reread(WordPressSite $site, string $type, int $id, array $fields): array
    {
        $response = $this->authenticated(
            $site,
            'GET',
            $site->route('wp/v2/'.$type.'/'.$id, ['context' => 'edit']),
        );

        $row = $response->json();

        $post = $this->postFromPayload($row, $fields, $type);

        $status = is_array($row) ? ($row['status'] ?? null) : null;

        if ($post === null || ! is_string($status) || $status === '') {
            // ⚠️ **UNREADABLE IS A FAILURE AND NOT A PASS.** A re-read we could
            // not make or could not parse leaves us with the update response's
            // word for it, which is the evidence this method exists because we
            // do not accept.
            throw WordPressRequestFailed::unreadable('read_back_response');
        }

        return [$post, $status];
    }

    /**
     * @param  array<string, string>  $fields
     *
     * @throws WordPressRequestFailed
     */
    private function assertTook(WordPressPost $post, array $fields, string $onMismatch): void
    {
        foreach ($fields as $name => $value) {
            if (! $this->matches($post->fields[$name] ?? '', $value)) {
                throw WordPressRequestFailed::unreadable($onMismatch);
            }
        }
    }

    /**
     * The slug a URL's last path segment gives, or a refusal.
     *
     * @throws WordPressRequestFailed
     */
    private function slugOf(string $url): string
    {
        $segments = $this->segmentsOf($url);

        if ($segments === []) {
            // The front page. See the class docblock: resolving it needs
            // `manage_options`, which §19.7's gate forbids.
            throw WordPressRequestFailed::unreadable('front_page_not_addressable');
        }

        return rawurldecode((string) end($segments));
    }

    /**
     * The slug a page may be **created** at, which is stricter than the slug one
     * may be found by.
     *
     * ⛔ **ONE SEGMENT, AND A NESTED ADDRESS IS REFUSED BY NAME** (5773).
     * `https://site/boiler-service` is a page whose slug is its whole address;
     * `https://site/services/boiler-service` is a page whose parent is
     * `services`, and core wants that parent's **id**. Resolving it means
     * looking up a page by the segment above and hoping it is the right one —
     * the same slug ambiguity 5593 refuses one level down, with a page creation
     * on the end of it instead of an edit. So it is refused here rather than
     * guessed at, and {@see self::create()}'s link check would catch it anyway.
     *
     * @throws WordPressRequestFailed
     */
    private function creatableSlugOf(string $url): string
    {
        $segments = $this->segmentsOf($url);

        if ($segments === []) {
            throw WordPressRequestFailed::unreadable('front_page_not_addressable');
        }

        if (count($segments) > 1) {
            throw WordPressRequestFailed::unreadable('nested_address_not_creatable');
        }

        return rawurldecode($segments[0]);
    }

    /**
     * @return list<string>
     */
    private function segmentsOf(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        return array_values(array_filter(
            explode('/', is_string($path) ? $path : ''),
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    /**
     * A change set's fields as core REST arguments.
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     *
     * @throws WordPressRequestFailed
     */
    private function payloadFor(array $fields): array
    {
        $payload = [];

        foreach ($fields as $name => $value) {
            $argument = self::WRITABLE_FIELDS[$name] ?? null;

            if ($argument === null) {
                throw WordPressRequestFailed::unreadable('unsupported_field');
            }

            $payload[$argument] = $value;
        }

        if ($payload === []) {
            throw WordPressRequestFailed::unreadable('empty_write');
        }

        return $payload;
    }

    /**
     * Undo a creation that went wrong, and say whether it worked.
     *
     * ⚠️ **A FAILED RETRACTION DOES NOT THROW — IT CHANGES THE LABEL.** The
     * caller is already throwing; what a person needs to know is whether a page
     * this platform created is still sitting on somebody's website, and that is
     * a different sentence from *"the address was wrong"*.
     */
    private function retract(WordPressSite $site, WordPressPost $post): bool
    {
        try {
            $this->unpublish($site, $post);
        } catch (WordPressRequestFailed) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $fields
     *
     * @throws WordPressRequestFailed
     */
    private function firstMatching(WordPressSite $site, string $type, string $slug, string $url, array $fields): ?WordPressPost
    {
        $endpoint = $site->route('wp/v2/'.$type, [
            'slug' => $slug,
            'context' => 'edit',
            // ⚠️ **PUBLISHED ONLY, AND THAT IS A DECISION RATHER THAN A
            // DEFAULT.** A change set is opened against a URL a visitor can
            // reach; a draft's `link` is a preview URL, so there is nothing to
            // compare against and nothing to snapshot.
            'status' => 'publish',
            'per_page' => 2,
        ]);

        $response = $this->authenticated($site, 'GET', $endpoint);

        $rows = $response->json();

        if (! is_array($rows)) {
            throw WordPressRequestFailed::unreadable('collection');
        }

        foreach ($rows as $row) {
            $candidate = $this->postFromPayload($row, $fields, $type);

            if ($candidate !== null && $this->sameUrl($candidate->link, $url)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fields
     */
    private function postFromPayload(mixed $row, array $fields, ?string $type = null): ?WordPressPost
    {
        if (! is_array($row)) {
            return null;
        }

        $id = $row['id'] ?? null;
        $link = $row['link'] ?? null;

        if (! is_int($id) || ! is_string($link)) {
            return null;
        }

        $values = [];

        foreach ($fields as $field) {
            $argument = self::WRITABLE_FIELDS[$field] ?? null;

            if ($argument === null) {
                return null;
            }

            // ⚠️ **`raw`, NEVER `rendered`.** `rendered` is the page after
            // shortcodes, `wpautop` and every content filter on the site — so a
            // snapshot taken from it could not be written back, and rule 32's
            // promise would be a string that puts a different page there.
            $raw = $row[$argument]['raw'] ?? null;

            if (! is_string($raw)) {
                return null;
            }

            $values[$field] = $raw;
        }

        $resolvedType = $type ?? $this->typeOf($row);

        if ($resolvedType === null) {
            return null;
        }

        return new WordPressPost($resolvedType, $id, $link, $values);
    }

    /**
     * ⚠️ **THE UPDATE RESPONSE CARRIES `type` (`post` / `page`) AND THE ROUTE
     * NEEDS THE REST BASE (`posts` / `pages`).** Naively pluralising anything
     * else — a custom post type whose base is not its name plus an `s` — is how
     * this becomes wrong later, so only the two core types are recognised.
     */
    private function typeOf(mixed $row): ?string
    {
        $type = is_array($row) ? ($row['type'] ?? null) : null;

        return match ($type) {
            'page' => 'pages',
            'post' => 'posts',
            default => null,
        };
    }

    /**
     * Whether two URLs name the same page.
     *
     * ⚠️ **QUERY AND FRAGMENT ARE IGNORED AND THE TRAILING SLASH IS NOT
     * SIGNIFICANT.** WordPress emits permalinks with a trailing slash under the
     * default structure and without one under others, and an owner pastes
     * whichever their browser showed. The host comparison is exact apart from
     * case: `www.` is a different site as far as WordPress is concerned, and
     * treating it as the same would let a change set aimed at one host land on
     * the other.
     */
    private function sameUrl(string $a, string $b): bool
    {
        $normalise = static function (string $url): string {
            $parts = parse_url($url);

            if (! is_array($parts)) {
                return '';
            }

            $host = mb_strtolower($parts['host'] ?? '');
            $path = rtrim($parts['path'] ?? '', '/');

            return $host.$path;
        };

        $left = $normalise($a);

        return $left !== '' && $left === $normalise($b);
    }

    /**
     * Whether what came back is what we asked to write.
     *
     * ⛔ **LINE ENDINGS AND OUTER WHITESPACE ARE NORMALISED, AND THIS SENTENCE
     * SAID "LINE ENDINGS AND NOTHING ELSE" UNTIL 2026-08-20 — CORRECTED (5984).**
     * The code has always called `trim()`, which is *something else*: a stored
     * value differing from ours only by leading or trailing whitespace is
     * reported as a match. **The defect was the docblock, not the code** — a
     * paragraph asserting a stricter comparison than the line beneath it makes,
     * in the class whose whole subject is not taking a claim for evidence, which
     * is 314–316's shape at its most ironic. The `trim()` is kept, because
     * WordPress and its editors move a trailing newline around freely and a
     * comparison that failed on one would be 5590's always-failing automation
     * for a difference no visitor can see.
     *
     * ⚠️ **AND NOTHING BEYOND THOSE TWO IS NORMALISED.** A stripped tag, a
     * rewritten attribute, an injected block — every one of them is the silent
     * filtering this comparison exists to catch, and softening it further would
     * turn the check back into a shape check, which is decision 5528's whole
     * argument one layer up.
     */
    private function matches(string $written, string $asked): bool
    {
        // ⚠️ **THE RULE MOVED AND THE ARGUMENT DID NOT** (6142).
        // `WordPressAdapter` now asks the same question of a page it is about to
        // put back — *is this still what we left* — so the comparison lives in
        // one place both can reach. See {@see SiteSnapshot::sameValue()}, which
        // carries the paragraph above verbatim.
        return SiteSnapshot::sameValue($written, $asked);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws WordPressRequestFailed
     */
    private function authenticated(WordPressSite $site, string $method, string $url, array $payload = []): Response
    {
        $this->assertReachableAddress($url);

        $request = $this->anonymous()
            ->withBasicAuth($site->username, $site->applicationPassword->reveal())
            // See the class docblock: a redirect off an authenticated request is
            // a working login handed to whatever the redirect names.
            ->withOptions(['allow_redirects' => false]);

        $response = $this->attempt(
            $method,
            $url,
            fn (): Response => match ($method) {
                'POST' => $request->post($url, $payload),
                'DELETE' => $request->delete($url),
                default => $request->get($url),
            },
        );

        return $this->classified($response);
    }

    /**
     * @throws WordPressRequestFailed
     */
    private function classified(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw match (true) {
            $response->status() === 401 => WordPressRequestFailed::unauthenticated(),
            $response->status() === 403 => WordPressRequestFailed::forbidden(),
            $response->status() === 404 => WordPressRequestFailed::notFound(),
            $response->status() === 429, $response->serverError() => WordPressRequestFailed::unavailable(),
            // ⚠️ A 3xx reaches here because redirects are not followed, and it
            // is not a success: the page this credential was pointed at moved.
            default => WordPressRequestFailed::unreadable('status_'.$response->status()),
        };
    }

    /**
     * @param  callable(): Response  $call
     *
     * @throws WordPressRequestFailed
     */
    private function attempt(string $method, string $url, callable $call): Response
    {
        $host = OutboundSiteBudget::hostOf($url);
        $brake = OutboundSiteBudget::reserve($host);

        if ($brake !== null) {
            // ⚠️ **NOT `VendorLog::timed()`, BECAUSE NOTHING WAS TIMED.** No
            // request was made, so there is no duration and no vendor status to
            // record; what is recorded is our own refusal, under a reason label
            // that says whose refusal it was.
            // ⛔ **AND THE LABEL WAS A FLAT `outbound_budget_exhausted` FOR ALL
            // THREE BRAKES UNTIL 9800–9819**, one of which is not a budget at
            // all: a host inside its own `Retry-After` was filed as our cap
            // being spent, in the only record either arm leaves — the counters
            // live in the cache and nothing writes a row. See
            // {@see OutboundSiteRefusal::vendorLogReason()} for why the
            // per-minute arm keeps the original string.
            VendorLog::failure('wordpress', $method, $url, $brake->vendorLogReason());

            throw WordPressRequestFailed::throttled($brake);
        }

        try {
            $response = VendorLog::timed('wordpress', $method, $url, $call);
        } catch (ConnectionException) {
            VendorLog::failure('wordpress', $method, $url, ConnectionException::class);

            throw WordPressRequestFailed::unreachable();
        }

        // ⛔ **HERE RATHER THAN IN {@see self::classified()}, BECAUSE THIS IS
        // THE FUNNEL AND THAT IS NOT.** `discover()` reaches a customer's host
        // through this method and never through `classified()`, so a `429`
        // answered at discovery would otherwise be the one refusal this
        // platform did not hear.
        OutboundSiteBudget::noteResponse($host, $response);

        return $response;
    }

    private function anonymous(): PendingRequest
    {
        return Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout($this->registry->int('wordpress.timeout_seconds'))
            ->acceptJson()
            // ⚠️ **BUILT FROM CONFIGURATION RATHER THAN WRITTEN OUT.** A site
            // owner reading their access log deserves to know who is editing
            // their pages, and a literal here would put our own hostname in a
            // file the outbound-inventory scanner reads as a vendor.
            ->withHeaders(['User-Agent' => config('app.name').' actuation (+'.config('app.url').')']);
    }

    /**
     * ⛔ **SSRF, AND IT IS NOT HYPOTHETICAL HERE.** The destination comes from
     * `locations.website_url`, which a business owner types into a form — the
     * only outbound URL in `app/` that does. `https://` is already required by
     * the store's CHECK constraint; this is the half that stops the address
     * being one of ours.
     *
     * ⚠️ **SLICE B OWNS THE OTHER HALF AND THIS DOES NOT WAIT FOR IT.**
     * §2.11.3 gives `website_url` a host-validated writer; until it lands, and
     * afterwards, a fetch made with a credential attached checks for itself.
     *
     * @throws WordPressRequestFailed
     */
    private function assertReachableAddress(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || mb_strtolower($parts['scheme'] ?? '') !== 'https') {
            throw WordPressRequestFailed::unreadable('not_https');
        }

        if (! PublicAddress::reaches($parts['host'] ?? '')) {
            throw WordPressRequestFailed::unreadable('address_not_public');
        }
    }
}
