<?php

/**
 * The HTTP surface GO AI EZ talks to.
 */
defined('ABSPATH') || exit;

/**
 * Ten routes, one permission callback, and no way in without this site's secret.
 *
 * ⛔ EVERY ROUTE HAS A REAL `permission_callback` AND NONE OF THEM IS
 * `__return_true`. WordPress has required the argument since 5.5 — *"If a
 * `permission_callback` is not provided, the REST API will issue a
 * `_doing_it_wrong` notice"*
 * (`developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/`,
 * page last updated 2024-09-18, fetched 2026-08-20) — and `__return_true` is
 * the documented way to satisfy it for a public endpoint. There is no public
 * endpoint here, and the platform's test suite fails the build if one appears.
 *
 * ⛔ NO ROUTE READS `WP_REST_Request::get_param()`, AND THAT IS A SECURITY RULE
 * RATHER THAN A STYLE. `get_param()` merges the query string, the body and the
 * URL together, and **the query string is not covered by the signature** — see
 * {@see Goaiez_Signature} for the full list of what is and is not signed. So
 * every handler reads `get_json_params()` and nothing else, and there is a lint.
 *
 * ⛔ AND NO ROUTE READS A REQUEST HEADER OTHER THAN THE THREE THE SIGNATURE
 * SCHEME NAMES, for the same reason: a fourth would be a value an attacker sets
 * and the signature does not cover. This is `CLAUDE.md` 314–316's fourth
 * instance said out loud — `SupportMailbox::fetch()` explained at length that a
 * sender-written header was forgeable and then trusted a different sender-written
 * header ten lines away. Naming the hazard is what makes people stop looking, so
 * here it is a lint instead.
 *
 * ## What a valid signature does not buy
 *
 * ⚠️ AUTHENTICATION IS NOT AUTHORISATION AND THEY ARE DELIBERATELY SEPARATE.
 * A signed request still meets the kill switch, the least-privilege gate on the
 * acting user, that user's per-post `edit_post` capability, and — for a script
 * deferral — this plugin's own copy of the never-defer list. Somebody holding a
 * stolen platform secret gets exactly what GO AI EZ itself gets, which is the
 * point of keeping that set small.
 */
class Goaiez_Rest
{
    const NAMESPACE_V1 = 'goaiez/v1';

    /**
     * Register every route.
     *
     * @return void
     */
    public static function register_routes()
    {
        $verify = [__CLASS__, 'verify'];

        $routes = [
            'status' => ['GET', 'status'],
            'page/snapshot' => ['POST', 'page_snapshot'],
            'page/write' => ['POST', 'page_write'],
            'page/create' => ['POST', 'page_create'],
            'page/revert' => ['POST', 'page_revert'],
            'indexnow-key' => ['POST', 'indexnow_key'],
            'sitemap' => ['POST', 'sitemap'],
            'site-report' => ['GET', 'site_report'],
            'speed-fix' => ['POST', 'speed_fix'],
            'disconnect' => ['POST', 'disconnect'],
        ];

        foreach ($routes as $route => $spec) {
            register_rest_route(
                self::NAMESPACE_V1,
                '/'.$route,
                [
                    'methods' => $spec[0],
                    'callback' => [__CLASS__, $spec[1]],
                    'permission_callback' => $verify,
                ]
            );
        }
    }

    /**
     * The permission callback: is this request really from GO AI EZ?
     *
     * @param  WP_REST_Request  $request  The request.
     * @return true|WP_Error
     */
    public static function verify($request)
    {
        $headers = [
            Goaiez_Signature::HEADER_SIGNATURE => $request->get_header(Goaiez_Signature::HEADER_SIGNATURE),
            Goaiez_Signature::HEADER_TIMESTAMP => $request->get_header(Goaiez_Signature::HEADER_TIMESTAMP),
            Goaiez_Signature::HEADER_NONCE => $request->get_header(Goaiez_Signature::HEADER_NONCE),
        ];

        $verdict = Goaiez_Signature::verify(
            $headers,
            $request->get_method(),
            $request->get_route(),
            $request->get_body(),
            Goaiez_Options::secret(),
            time(),
            new Goaiez_Transient_Nonce_Store
        );

        if ($verdict === true) {
            return true;
        }

        // ⚠️ THE REASON IS RETURNED AND THAT IS A CONSIDERED CHOICE. It tells an
        // attacker nothing they cannot already establish by trying — a stale
        // timestamp is visible from the clock, a missing header from the
        // request they just sent — and it is the difference between our own
        // operator diagnosing a clock-skew problem in a minute and diagnosing it
        // in a day. No secret, no capability and no site detail is in any of the
        // seven codes.
        return new WP_Error('goaiez_'.$verdict, 'This request was not accepted.', ['status' => 401]);
    }

    /**
     * The body of a request, which is the only place a parameter may come from.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return array
     */
    private static function body($request)
    {
        $params = $request->get_json_params();

        return is_array($params) ? $params : [];
    }

    /**
     * One string from the signed body.
     *
     * @param  array  $body  The body.
     * @param  string  $key  The key.
     * @return string
     */
    private static function str(array $body, $key)
    {
        return isset($body[$key]) && is_scalar($body[$key]) ? (string) $body[$key] : '';
    }

    /**
     * The kill switch, for the routes that are not a page write.
     *
     * @return true|WP_Error
     */
    private static function require_write_access()
    {
        if (! Goaiez_Options::write_access()) {
            return new WP_Error('goaiez_write_access_off', 'Write access is switched off on this site.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Everything the platform needs to know about this site's arrangement.
     *
     * ⚠️ IT ANSWERS WHILE WRITE ACCESS IS OFF, ON PURPOSE. A status route that
     * went silent with the kill switch would leave the platform unable to tell
     * "the owner switched us off" from "the site is gone", and those are
     * different sentences to say to a customer.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response
     */
    public static function status($request)
    {
        $actor = Goaiez_Options::actor();

        return new WP_REST_Response(
            [
                'plugin_version' => GOAIEZ_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'multisite' => is_multisite(),
                'write_access' => Goaiez_Options::write_access(),
                'paired_at' => Goaiez_Options::paired_at(),
                'actor' => [
                    'user_id' => $actor,
                    'refusal' => Goaiez_Capabilities::refusal_for_user($actor),
                ],
                'writable_fields' => array_keys(Goaiez_Writer::FIELDS),
                'speed_support' => Goaiez_Speed::support(),
                'live_speed_fixes' => array_keys(Goaiez_Options::speed_fixes()),
                'indexnow' => [
                    'key_present' => Goaiez_Options::indexnow_key() !== '',
                    'key_location' => Goaiez_IndexNow::key_location(),
                ],
                'robots' => Goaiez_Robots::report(),
                'seo_plugin' => self::seo_plugin(),
                'home_url' => home_url('/'),
            ],
            200
        );
    }

    /**
     * Read a page's current values.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function page_snapshot($request)
    {
        $body = self::body($request);
        $post_id = Goaiez_Writer::locate(self::str($body, 'url'));

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if ($post_id <= 0) {
            // ⛔ ABSENCE IS AN ANSWER AND NOT AN ERROR (5774). "There is no page
            // here" is what a creation proceeds from; "we could not read the
            // site" is not, and reading one as the other creates a page on top
            // of one that already exists.
            return new WP_REST_Response(['present' => false, 'fields' => []], 200);
        }

        $fields = isset($body['fields']) && is_array($body['fields']) ? array_map('strval', $body['fields']) : array_keys(Goaiez_Writer::FIELDS);

        $snapshot = Goaiez_Writer::snapshot($post_id, $fields);

        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        return new WP_REST_Response(
            [
                'present' => true,
                'post_id' => $post_id,
                'url' => get_permalink($post_id),
                'fields' => $snapshot,
            ],
            200
        );
    }

    /**
     * Apply a change set.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function page_write($request)
    {
        $body = self::body($request);
        $result = Goaiez_Writer::write(
            [
                'change_id' => self::str($body, 'change_id'),
                'url' => self::str($body, 'url'),
                'summary' => self::str($body, 'summary'),
                'after' => isset($body['after']) && is_array($body['after']) ? $body['after'] : [],
            ]
        );

        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    /**
     * Create a page.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function page_create($request)
    {
        $body = self::body($request);
        $result = Goaiez_Writer::create(
            [
                'change_id' => self::str($body, 'change_id'),
                'url' => self::str($body, 'url'),
                'summary' => self::str($body, 'summary'),
                'after' => isset($body['after']) && is_array($body['after']) ? $body['after'] : [],
            ]
        );

        return is_wp_error($result) ? $result : new WP_REST_Response($result, 201);
    }

    /**
     * Undo a change.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function page_revert($request)
    {
        $body = self::body($request);
        $result = Goaiez_Writer::revert(self::str($body, 'change_id'), 'platform');

        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    /**
     * Set or clear the IndexNow key this site serves.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function indexnow_key($request)
    {
        $allowed = self::require_write_access();

        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $key = self::str(self::body($request), 'key');

        if ($key !== '' && ! Goaiez_IndexNow::is_well_formed($key)) {
            // ⛔ REFUSED BEFORE IT IS STORED, BECAUSE A MALFORMED KEY IS
            // INDISTINGUISHABLE FROM A MISSING FILE ONCE IT IS LIVE. IndexNow
            // answers both with the same 403 — *"key not valid (e.g. key not
            // found, file found but key not in the file)"* — which is the
            // finding at the end of decision block 5680–5699.
            return new WP_Error('goaiez_malformed_key', 'That is not a valid IndexNow key.', ['status' => 422]);
        }

        Goaiez_Options::set_indexnow_key($key);

        return new WP_REST_Response(
            [
                'key_present' => $key !== '',
                'key_location' => Goaiez_IndexNow::key_location(),
            ],
            200
        );
    }

    /**
     * Turn the robots.txt sitemap announcement on or off.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function sitemap($request)
    {
        $allowed = self::require_write_access();

        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $body = self::body($request);

        Goaiez_Options::set_announce_sitemap(! empty($body['announce']));

        return new WP_REST_Response(Goaiez_Robots::report(), 200);
    }

    /**
     * What this site loads, so the speed layer has something to compute from.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response
     */
    public static function site_report($request)
    {
        Goaiez_Speed::want_inventory();

        return new WP_REST_Response(
            [
                'inventory' => Goaiez_Options::inventory(),
                'support' => Goaiez_Speed::support(),
            ],
            200
        );
    }

    /**
     * Turn one speed fix on or off.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response|WP_Error
     */
    public static function speed_fix($request)
    {
        $allowed = self::require_write_access();

        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $body = self::body($request);
        $field = self::str($body, 'field');

        if (! empty($body['withdraw'])) {
            Goaiez_Speed::withdraw($field);

            return new WP_REST_Response(['live' => array_keys(Goaiez_Options::speed_fixes())], 200);
        }

        $result = Goaiez_Speed::apply($field, isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : []);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['live' => array_keys(Goaiez_Options::speed_fixes())], 200);
    }

    /**
     * Give the site back.
     *
     * ⛔ IT ANSWERS EVEN WITH THE KILL SWITCH THROWN, AND EVEN THOUGH IT IS THE
     * MOST DESTRUCTIVE ROUTE HERE. Refusing a relinquish because writes are off
     * would leave a customer who has already switched us off still holding a
     * working secret of ours, which is the state the switch exists to end.
     *
     * ⚠️ THE CHANGE LOG SURVIVES. Everything that lets us back in goes; the
     * record of what was done to this website stays, because it is the owner's.
     *
     * @param  WP_REST_Request  $request  The request.
     * @return WP_REST_Response
     */
    public static function disconnect($request)
    {
        Goaiez_Options::set_speed_fixes([]);
        Goaiez_Options::set_indexnow_key('');
        Goaiez_Options::set_announce_sitemap(false);
        Goaiez_Options::set_actor(0);
        Goaiez_Options::forget_secret();

        return new WP_REST_Response(['disconnected' => true], 200);
    }

    /**
     * Which SEO plugin, if any, is managing this site's meta descriptions.
     *
     * ⚠️ REPORTED AND NEVER WRITTEN. Decision 5591: WordPress core has no meta
     * description, it is an SEO plugin's registered post meta under that
     * plugin's own private key, and writing another plugin's private meta is
     * something to do after reading that plugin's own documentation — which 5591
     * names as owed to a later slice. What the platform has never had is the
     * answer to *"which one is it"*, which turns a guess into a lookup.
     *
     * @return string
     */
    private static function seo_plugin()
    {
        if (defined('WPSEO_VERSION')) {
            return 'yoast';
        }

        if (defined('RANK_MATH_VERSION')) {
            return 'rank_math';
        }

        if (defined('AIOSEO_VERSION')) {
            return 'aioseo';
        }

        if (defined('SEOPRESS_VERSION')) {
            return 'seopress';
        }

        return '';
    }
}
