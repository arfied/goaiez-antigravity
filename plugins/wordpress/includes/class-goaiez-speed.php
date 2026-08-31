<?php
/**
 * `28` §4.1's seven speed fixes, and what this plugin can honestly do about each.
 *
 * @package Goaiez
 */

defined( 'ABSPATH' ) || exit;

/**
 * The speed layer's hands, and the eyes it never had.
 *
 * ⛔ TWO SEPARATE THINGS WERE MISSING AND THIS CLASS IS BOTH OF THEM (5850,
 * 5852). The first is a way to apply a fix: core REST writes `title`, `content`
 * and `excerpt` and nothing else, and every one of the seven is a filter, a
 * stylesheet line or a rewrite rule. The second is larger — *"granted a way to
 * write, nothing here knows **what** to write: script deferral needs the list of
 * scripts a page loads, preconnect its third-party origins"* — because the pixel
 * collects no resource timings and `SiteProbe` keeps two booleans from a page
 * fetch. {@see self::inventory()} is that missing input, and it is the reason
 * this plugin is the only thing that can both see a site well enough to compute
 * a fix and apply one.
 *
 * ⛔ AND FOUR OF THE SEVEN ARE APPLIED, ONE IS OWED AND TWO ARE REFUSED, WHICH
 * CONTRADICTS DOC `41` PART 1's T1 ROW. That table promises WordPress *"All 7
 * fixes"*. {@see self::support()} is what this plugin will actually do, with a
 * reason per refusal; the contradiction is reported rather than resolved here,
 * because the coverage matrix is *"the single source for any owner-facing claim
 * about site control"* and narrowing it is a ruling.
 *
 * ⛔ NOTHING HERE IS ON BY DEFAULT. Every fix is off until the platform sends a
 * signed request switching it on, each one is its own change set, and
 * deactivating the plugin removes all of them at once because they are filters
 * rather than edits — which is rule 32's reversibility holding by construction
 * rather than by bookkeeping.
 */
class Goaiez_Speed {

	/**
	 * Hosts that are never deferred, whatever the platform asks for.
	 *
	 * ⛔ THIS IS A COPY OF `App\Services\Actuation\ScriptDeferral::
	 * FORBIDDEN_HOSTS` AND IT IS THE LAST LINE RATHER THAN THE FIRST. The
	 * platform already refuses these — `28` §4.2's *"never defer: payment
	 * scripts, booking engines, anything matching a form or checkout
	 * signature"*, with an ordering argued at decision 5864 so the refusal stays
	 * falsifiable. The copy exists for the case that list cannot cover: somebody
	 * holding a stolen platform secret, or a platform bug, asking this site to
	 * defer its own checkout. A local business losing payments is not a thing to
	 * leave to one layer.
	 *
	 * ⚠️ A COPY DRIFTS, SO THE PLATFORM'S TEST SUITE PARSES THIS FILE AND FAILS
	 * THE BUILD WHEN THE TWO LISTS STOP MATCHING.
	 *
	 * @var string[]
	 */
	const NEVER_DEFER_HOSTS = array(
		// Payments.
		'js.stripe.com',
		'checkout.stripe.com',
		'www.paypal.com',
		'www.paypalobjects.com',
		'js.braintreegateway.com',
		'web.squarecdn.com',
		'js.squareup.com',
		'x.klarnacdn.net',
		'checkout.razorpay.com',
		'js.authorize.net',
		'pay.google.com',
		// Booking engines.
		'assets.calendly.com',
		'embed.acuityscheduling.com',
		'cdn.mindbodyonline.com',
		'book.squareup.com',
	);

	/**
	 * Substrings that are never deferred, matched in the host and the path.
	 *
	 * ⚠️ NEVER THE QUERY STRING (5529, 5865): a `cart` signature matched against
	 * a query would refuse a tag-manager container called `GTM-CART` for a
	 * reason that has nothing to do with a shopping cart.
	 *
	 * @var string[]
	 */
	const NEVER_DEFER_SIGNATURES = array(
		'checkout',
		'payment',
		'/pay',
		'billing',
		'stripe',
		'paypal',
		'braintree',
		'adyen',
		'klarna',
		'authorize.net',
		'booking',
		'book-now',
		'reserve',
		'appointment',
		'schedul',
		'cart',
		'recaptcha',
		'hcaptcha',
		'turnstile',
	);

	/** How long the platform's request for a fresh site inventory stays armed. */
	const INVENTORY_WINDOW_SECONDS = 600;

	/** The most scripts, styles or origins any inventory will report. */
	const INVENTORY_LIMIT = 100;

	/**
	 * What this plugin will do with each of `28` §4.1's seven, and why not for
	 * the rest.
	 *
	 * ⛔ THIS IS `CmsAdapter::fieldSupport()`'s ANSWER, PHRASED FOR THE SEAM
	 * THAT ALREADY EXISTS (5772, 5851). `SpeedFixes::plan()` asks the adapter
	 * what it can write and records `SpeedFixRefusal::AdapterCannotWrite`
	 * against the rest. Nothing in `SpeedFix` moves when a plugin adapter reads
	 * this; it answers differently, which is the whole design.
	 *
	 * @return array Field name => array( supported, reason ).
	 */
	public static function support() {
		return array(
			'deferred_scripts'   => array( true, '' ),
			'preconnect_origins' => array( true, '' ),
			'font_display'       => array( true, 'Only for fonts loaded from fonts.googleapis.com. A self-hosted font-face rule lives in a theme stylesheet, and rewriting a theme file would mean this plugin holding write access to the site\'s files, which it never does.' ),
			'embed_reservations' => array( true, '' ),

			// ⚠️ OWED RATHER THAN REFUSED. Implementable — read the local image
			// file's dimensions and add them — and deliberately not built in
			// this slice, because it means rewriting post content HTML on every
			// front-end render and that is its own risk surface on somebody
			// else's website.
			'image_dimensions'   => array( false, 'Not built yet. WordPress already sizes every image it can identify as one of its own; the rest need this plugin to measure the file, which is owed to a later release.' ),

			// ⛔ REFUSED ON EVIDENCE, NOT DEFERRED. WordPress has lazy-loaded
			// by default since 5.5 for *"all img tags that have width and height
			// attributes present"*, and *"width and height can only be
			// determined if an image is for a WordPress attachment"*
			// (`make.wordpress.org/core/2020/07/14/lazy-loading-images-in-5-5/`,
			// published 2020-07-14, fetched 2026-08-20). So the images core
			// skips are exactly the unsized ones — and lazy-loading an unsized
			// image causes the layout shift the sizing fix exists to prevent.
			// Decision 5854's chain, taken to its conclusion: doing this before
			// image_dimensions makes the site worse.
			'lazy_loading'       => array( false, 'WordPress already does this for every image it can measure, and doing it to the images it cannot measure would make the page jump about as it loads.' ),

			// ⛔ PERMANENTLY REFUSED, AND ON ONE OF OUR OWN RULES RATHER THAN A
			// CAPABILITY GAP. `28` §4.1 specifies *"on-server convert"* — which
			// means this plugin writing image files into a customer's uploads
			// directory. `19` §3.3 and `CLAUDE.md` make filesystem write access
			// to a stranger's server the thing this plugin is built not to have,
			// and there is a lint asserting it never opens a file for writing.
			// It is reachable at the edge tier, which is not built.
			'image_formats'      => array( false, 'This plugin never writes files to your server, so it cannot convert your images. That fix needs the edge tier.' ),
		);
	}

	/**
	 * Attach whatever fixes are currently live.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'style_loader_src', array( __CLASS__, 'maybe_swap_google_font_display' ), 20, 2 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'maybe_defer_script' ), 20, 3 );
		add_filter( 'wp_resource_hints', array( __CLASS__, 'maybe_add_preconnects' ), 20, 2 );
		add_action( 'wp_head', array( __CLASS__, 'maybe_reserve_embed_space' ), 5 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'maybe_take_inventory' ), PHP_INT_MAX );
	}

	/**
	 * The payload of one live fix, or null.
	 *
	 * @param string $field The field name from {@see self::support()}.
	 * @return array|null
	 */
	private static function live( $field ) {
		$fixes = Goaiez_Options::speed_fixes();

		return isset( $fixes[ $field ] ) && is_array( $fixes[ $field ] ) ? $fixes[ $field ] : null;
	}

	/**
	 * Turn one fix on, with its content.
	 *
	 * @param string $field   Field name.
	 * @param array  $payload The fix's content.
	 * @return true|WP_Error
	 */
	public static function apply( $field, array $payload ) {
		$support = self::support();

		if ( ! isset( $support[ $field ] ) ) {
			return new WP_Error( 'goaiez_unknown_fix', 'No such speed change.' );
		}

		if ( ! $support[ $field ][0] ) {
			return new WP_Error( 'goaiez_fix_not_supported', $support[ $field ][1] );
		}

		if ( 'deferred_scripts' === $field ) {
			$refused = self::refused_handles( isset( $payload['handles'] ) ? (array) $payload['handles'] : array() );

			if ( array() !== $refused ) {
				return new WP_Error(
					'goaiez_never_defer',
					'These are payment, booking or form scripts and this plugin will not delay them: ' . implode( ', ', $refused )
				);
			}
		}

		$fixes            = Goaiez_Options::speed_fixes();
		$fixes[ $field ]  = $payload;

		Goaiez_Options::set_speed_fixes( $fixes );

		return true;
	}

	/**
	 * Turn one fix off.
	 *
	 * @param string $field Field name.
	 * @return true
	 */
	public static function withdraw( $field ) {
		$fixes = Goaiez_Options::speed_fixes();

		unset( $fixes[ $field ] );

		Goaiez_Options::set_speed_fixes( $fixes );

		return true;
	}

	/**
	 * Which of these script handles this plugin refuses to defer.
	 *
	 * @param array $handles Registered script handles.
	 * @return string[]
	 */
	public static function refused_handles( array $handles ) {
		$refused = array();

		foreach ( $handles as $handle ) {
			$src = self::script_src( (string) $handle );

			if ( null === $src || ! self::may_defer( $src ) ) {
				$refused[] = (string) $handle;
			}
		}

		return $refused;
	}

	/**
	 * Whether a script URL may be deferred at all.
	 *
	 * ⚠️ A URL WITH NO HOST IS REFUSED. Everything below is a fact about a host,
	 * so a string without one is a string about which no safety claim can be
	 * made — `ScriptDeferral::mayDefer()`'s own rule.
	 *
	 * @param string $src The script URL.
	 * @return bool
	 */
	public static function may_defer( $src ) {
		// ⚠️ `parse_url()` RATHER THAN `wp_parse_url()`, AND THE REASON IS THAT
		// THIS METHOD IS TESTED. `wp_parse_url()` exists to work around
		// `parse_url()` mishandling protocol-relative URLs on PHP below 5.4.7,
		// and this plugin declares `Requires PHP: 7.4`. Using the native
		// function makes the never-defer decision plain PHP, so the platform's
		// own suite drives it directly — which for the one rule that stops a
		// stolen secret delaying somebody's checkout is worth more than
		// consistency with the wrapper used elsewhere.
		$parts = parse_url( (string) $src );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

		if ( '' === $host ) {
			return false;
		}

		foreach ( self::NEVER_DEFER_HOSTS as $forbidden ) {
			if ( $host === $forbidden ) {
				return false;
			}
		}

		$path    = isset( $parts['path'] ) ? $parts['path'] : '';
		$subject = strtolower( $host . $path );

		foreach ( self::NEVER_DEFER_SIGNATURES as $signature ) {
			if ( false !== strpos( $subject, $signature ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add `defer` to the scripts the platform named.
	 *
	 * ⚠️ THE HOST IS RE-CHECKED HERE AND NOT ONLY WHEN THE FIX WAS SWITCHED ON.
	 * A handle's source can change when a theme or plugin updates, and the fix
	 * outlives the request that set it.
	 *
	 * @param string $tag    The `<script>` tag.
	 * @param string $handle The registered handle.
	 * @param string $src    The script URL.
	 * @return string
	 */
	public static function maybe_defer_script( $tag, $handle, $src ) {
		$fix = self::live( 'deferred_scripts' );

		if ( null === $fix || is_admin() ) {
			return $tag;
		}

		$handles = isset( $fix['handles'] ) ? (array) $fix['handles'] : array();

		if ( ! in_array( (string) $handle, array_map( 'strval', $handles ), true ) ) {
			return $tag;
		}

		if ( ! self::may_defer( $src ) ) {
			return $tag;
		}

		if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}

		return str_replace( ' src=', ' defer src=', $tag );
	}

	/**
	 * `28` §4.1's *"preconnect hints (top 3 third-party origins)"*.
	 *
	 * ⛔ THE THREE COME FROM THE PLATFORM, WHICH GOT THEM FROM
	 * {@see self::inventory()}. Decision 5853 refused to build this at T3
	 * because the origin list would always be empty — *"256's vacuous pass with
	 * a `<link>` tag on it"*. It is not empty here, and that is the whole
	 * difference the plugin makes.
	 *
	 * @param array  $hints The URLs already hinted.
	 * @param string $relation The relation type.
	 * @return array
	 */
	public static function maybe_add_preconnects( $hints, $relation ) {
		if ( 'preconnect' !== $relation ) {
			return $hints;
		}

		$fix = self::live( 'preconnect_origins' );

		if ( null === $fix ) {
			return $hints;
		}

		$origins = isset( $fix['origins'] ) ? (array) $fix['origins'] : array();
		$added   = 0;

		foreach ( $origins as $origin ) {
			if ( $added >= 3 ) {
				break;
			}

			$origin = esc_url_raw( (string) $origin );

			if ( '' !== $origin && ! in_array( $origin, $hints, true ) ) {
				$hints[] = $origin;
				++$added;
			}
		}

		return $hints;
	}

	/**
	 * `font-display: swap` on Google Fonts stylesheets.
	 *
	 * ⚠️ THIS IS THE ONLY FONT THIS PLUGIN CAN REACH AND {@see self::support()}
	 * SAYS SO OUT LOUD. `font-display` is a descriptor inside an `@font-face`
	 * rule, so changing it for a self-hosted font means editing the stylesheet
	 * that declares it — a theme file. Google's own API takes it as a query
	 * parameter, which is a URL rewrite and needs nobody's filesystem.
	 *
	 * ⛔ ON `style_loader_src` RATHER THAN `wp_enqueue_scripts`, AND THE FIRST
	 * DRAFT HAD IT WRONG IN THE WAY THAT LOOKS RIGHT. Hooking
	 * `wp_enqueue_scripts` at priority 1 runs *before* the theme has enqueued
	 * anything, so the stylesheet this fix is for is not registered yet and the
	 * loop finds nothing — a fix that reports itself live, changes nothing, and
	 * is then measured, judged and quarantined as though it had.
	 * `style_loader_src` fires once per stylesheet as it is printed, which is
	 * the last moment the source can be changed and the first moment it is
	 * certainly there.
	 *
	 * @param string $src    The stylesheet URL.
	 * @param string $handle The registered handle.
	 * @return string
	 */
	public static function maybe_swap_google_font_display( $src, $handle ) {
		if ( null === self::live( 'font_display' ) || ! is_string( $src ) || '' === $src ) {
			return $src;
		}

		$host = wp_parse_url( $src, PHP_URL_HOST );

		if ( 'fonts.googleapis.com' !== $host || false !== strpos( $src, 'display=' ) ) {
			return $src;
		}

		return add_query_arg( 'display', 'swap', $src );
	}

	/**
	 * `28` §4.1's *"reserve space for common embeds (maps, YouTube, booking)"*.
	 *
	 * ⚠️ A STYLE RULE AND NEVER A DOM REWRITE. An `aspect-ratio` on the
	 * iframe's own selector holds the space before the frame arrives without
	 * this plugin touching anybody's markup, so undoing it is deleting a
	 * `<style>` block that only exists while the fix is live.
	 *
	 * @return void
	 */
	public static function maybe_reserve_embed_space() {
		$fix = self::live( 'embed_reservations' );

		if ( null === $fix ) {
			return;
		}

		$ratio = isset( $fix['ratio'] ) ? (string) $fix['ratio'] : '16 / 9';

		if ( 1 !== preg_match( '/^[0-9]{1,4} \/ [0-9]{1,4}$/', $ratio ) ) {
			$ratio = '16 / 9';
		}

		echo '<style id="goaiez-embed-space">.wp-block-embed__wrapper iframe,.wp-embed-responsive iframe{aspect-ratio:' . esc_attr( $ratio ) . ';width:100%;height:auto;}</style>' . "\n";
	}

	/**
	 * Ask this site what it loads.
	 *
	 * ⛔ THIS IS DECISION 5852's MISSING INPUT AND IT IS THE LARGER HALF OF THE
	 * SLICE. *"Nothing here knows a site's scripts, fonts, images or third-party
	 * origins"* — the pixel collects no resource timings, and `SiteProbe` keeps
	 * two booleans from a page fetch and none of the markup. WordPress knows all
	 * of it, because it is what enqueued it.
	 *
	 * ⚠️ IT RUNS ONLY WHEN THE PLATFORM HAS ASKED, INSIDE A TEN-MINUTE WINDOW,
	 * AND WRITES ONCE. Collecting on every page view would mean an option write
	 * on every request to a customer's website, for a report nobody had asked
	 * for — the marginal-cost tiebreaker landing on somebody else's server.
	 *
	 * ⚠️ AND IT IS THE FRONT END ONLY. An admin page's scripts are not the
	 * scripts a visitor waits for.
	 *
	 * @return void
	 */
	public static function maybe_take_inventory() {
		if ( is_admin() || ! get_transient( 'goaiez_inventory_wanted' ) ) {
			return;
		}

		delete_transient( 'goaiez_inventory_wanted' );

		Goaiez_Options::set_inventory( self::inventory() );
	}

	/**
	 * What this page render loaded.
	 *
	 * @return array
	 */
	public static function inventory() {
		global $wp, $wp_scripts, $wp_styles;

		$home    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$scripts = array();
		$styles  = array();
		$origins = array();

		if ( isset( $wp_scripts ) && is_object( $wp_scripts ) ) {
			foreach ( (array) $wp_scripts->done as $handle ) {
				$src = self::script_src( (string) $handle );

				if ( null === $src || count( $scripts ) >= self::INVENTORY_LIMIT ) {
					continue;
				}

				$scripts[] = array(
					'handle'    => (string) $handle,
					'src'       => $src,
					'host'      => (string) wp_parse_url( $src, PHP_URL_HOST ),
					'may_defer' => self::may_defer( $src ),
				);
			}
		}

		if ( isset( $wp_styles ) && is_object( $wp_styles ) ) {
			foreach ( (array) $wp_styles->done as $handle ) {
				if ( ! isset( $wp_styles->registered[ $handle ] ) || count( $styles ) >= self::INVENTORY_LIMIT ) {
					continue;
				}

				$src = $wp_styles->registered[ $handle ]->src;

				if ( ! is_string( $src ) || '' === $src ) {
					continue;
				}

				$styles[] = array(
					'handle' => (string) $handle,
					'src'    => $src,
					'host'   => (string) wp_parse_url( $src, PHP_URL_HOST ),
				);
			}
		}

		foreach ( array_merge( $scripts, $styles ) as $asset ) {
			$host = strtolower( (string) $asset['host'] );

			if ( '' !== $host && $host !== $home && ! isset( $origins[ $host ] ) && count( $origins ) < self::INVENTORY_LIMIT ) {
				$origins[ $host ] = 'https://' . $host;
			}
		}

		return array(
			'taken_at'     => time(),
			// ⚠️ BUILT FROM `WP::$request`, WHICH IS THE PATH RELATIVE TO HOME.
			// `home_url( add_query_arg( array() ) )` is the idiom everybody
			// reaches for and it doubles the directory on a subdirectory
			// install, because `add_query_arg( array() )` returns the whole
			// request URI including that directory.
			'url'          => home_url( '/' . ( isset( $wp ) && isset( $wp->request ) ? (string) $wp->request : '' ) ),
			'scripts'      => $scripts,
			'styles'       => $styles,
			'third_party'  => array_values( $origins ),
		);
	}

	/**
	 * Ask for a fresh inventory on the next front-end page view.
	 *
	 * @return void
	 */
	public static function want_inventory() {
		set_transient( 'goaiez_inventory_wanted', 1, self::INVENTORY_WINDOW_SECONDS );
	}

	/**
	 * A registered script's URL, or null.
	 *
	 * @param string $handle The handle.
	 * @return string|null
	 */
	private static function script_src( $handle ) {
		global $wp_scripts;

		if ( ! isset( $wp_scripts ) || ! is_object( $wp_scripts ) || ! isset( $wp_scripts->registered[ $handle ] ) ) {
			return null;
		}

		$src = $wp_scripts->registered[ $handle ]->src;

		if ( ! is_string( $src ) || '' === $src ) {
			return null;
		}

		return 0 === strpos( $src, '//' ) || false !== strpos( $src, '://' ) ? $src : home_url( $src );
	}
}
