<?php
/**
 * Verifying that a request really came from GO AI EZ.
 *
 * @package Goaiez
 */

defined( 'ABSPATH' ) || exit;

/**
 * The one thing standing between a stranger on the internet and write access to
 * this website.
 *
 * ⛔ THIS CLASS CALLS NO WORDPRESS FUNCTION, ON PURPOSE. It is plain PHP so that
 * the platform's own test suite can `require` it and drive it directly — a
 * wrong signature, a replay, a body altered after signing, no signature at all —
 * rather than only through a route that no test in that repository can reach,
 * because no WordPress runs there. Asserting at the verifier rather than at the
 * route is decision 4965's lesson: the pixel collector's first isolation test
 * passed for the wrong reason because it went through the route.
 *
 * ## What is signed, exactly
 *
 * The canonical string is six lines joined with "\n":
 *
 *     GOAIEZ-HMAC-SHA256-v1
 *     {HTTP method, upper case}
 *     {the REST route, e.g. /goaiez/v1/page/write}
 *     {unix timestamp, seconds}
 *     {nonce}
 *     {sha256 hex of the raw request body}
 *
 * ⛔ EVERY VALUE IN THAT LIST IS ATTACKER-CONTROLLED AND THAT IS THE POINT.
 * They are written by whoever made the request; the signature is what says the
 * person who wrote them also holds this site's secret. Naming them is not
 * reassurance, it is the list of things a reader has to check are covered.
 *
 * ⛔ AND WHAT IS *NOT* COVERED IS THE PART THAT BITES. `SupportMailbox::fetch()`
 * is this codebase's most expensive security defect (`CLAUDE.md` 314–316's
 * fourth instance): a long docblock explaining that a sender-written header is
 * forgeable, ten lines above code that trusted a different sender-written header
 * for exactly the same job. So, plainly:
 *
 * - **No other header is covered.** Nothing in this plugin may read a request
 *   header other than the three named below, because a fourth would be a value
 *   an attacker sets and the signature does not cover. There is a lint.
 * - **The query string is not covered.** The route is, because WordPress derives
 *   it from `rest_route` and it is *what selected this handler* — change it and
 *   a different handler runs against a signature computed over the old one, so
 *   it fails. But `?anything=else` is invisible here. Therefore **every route
 *   handler reads its parameters from the JSON body only**, never
 *   `WP_REST_Request::get_param()`, which merges the query string in. There is a
 *   lint for that too.
 * - **The host is not covered**, so a signed request captured from site A would
 *   verify at site B if the two shared a secret. They never do: the secret is
 *   generated on this site, by this site, and is not derived from anything the
 *   platform holds for anybody else.
 * - **A valid signature says nothing about what the request may do.** It
 *   authenticates; the kill switch, the acting user's capabilities and the
 *   never-defer list are what authorise. They are separate on purpose.
 *
 * ## Replay
 *
 * A signature is good for {@see self::SKEW_SECONDS} either side of its
 * timestamp, and its nonce may be used once. `19` §3.3 asks for "short-lived
 * tokens"; a single-use five-minute signature is that, without a token endpoint
 * to defend.
 *
 * ⛔ THE NONCE STORE'S LIFETIME MUST OUTLAST THE SIGNATURE'S, OR THE REPLAY
 * WINDOW REOPENS WHILE THE TIMESTAMP IS STILL FRESH. A signature is acceptable
 * at any observed time within SKEW of its timestamp, so its validity is 2×SKEW
 * wide; a nonce forgotten sooner than that is a nonce a replay outlives.
 * {@see self::NONCE_TTL_SECONDS} is that arithmetic and a test pins the
 * relationship rather than the numbers.
 *
 * ⛔ THE NONCE IS RECORDED ONLY AFTER THE SIGNATURE VERIFIES. Recording first
 * would let anyone who can reach this URL fill the store with nonces they made
 * up, and the store is finite.
 */
interface Goaiez_Nonce_Store {

	/**
	 * Whether this nonce has already been spent, inside the TTL.
	 *
	 * @param string $nonce The nonce.
	 * @return bool
	 */
	public function seen( $nonce );

	/**
	 * Record a nonce as spent.
	 *
	 * @param string $nonce The nonce.
	 * @param int    $ttl   Seconds to remember it for.
	 * @return void
	 */
	public function spend( $nonce, $ttl );
}

/**
 * The nonce store WordPress gives us: the transients API, which is either the
 * options table or the site's object cache.
 *
 * ⚠️ A TRANSIENT CAN BE EVICTED EARLY BY AN OBJECT CACHE UNDER MEMORY PRESSURE,
 * so this is best-effort and is stated as such rather than claimed as a
 * guarantee. What it is not is the only defence: the five-minute skew window
 * bounds any replay whether or not the nonce survived, and every write this
 * plugin performs is idempotent on the platform's own change id — a replayed
 * write re-applies the same values to the same page.
 */
class Goaiez_Transient_Nonce_Store implements Goaiez_Nonce_Store {

	/**
	 * Whether this nonce has already been spent.
	 *
	 * @param string $nonce The nonce.
	 * @return bool
	 */
	public function seen( $nonce ) {
		return false !== get_transient( 'goaiez_nonce_' . hash( 'sha256', (string) $nonce ) );
	}

	/**
	 * Record a nonce as spent.
	 *
	 * @param string $nonce The nonce.
	 * @param int    $ttl   Seconds to remember it for.
	 * @return void
	 */
	public function spend( $nonce, $ttl ) {
		set_transient( 'goaiez_nonce_' . hash( 'sha256', (string) $nonce ), 1, (int) $ttl );
	}
}

class Goaiez_Signature {

	/**
	 * The signature scheme, first line of the canonical string.
	 *
	 * ⚠️ IT IS IN THE SIGNED MATERIAL SO THAT A FUTURE v2 CANNOT BE FED A v1
	 * SIGNATURE. A scheme name carried only in a header is a value the attacker
	 * chooses.
	 */
	const SCHEME = 'GOAIEZ-HMAC-SHA256-v1';

	const HEADER_SIGNATURE = 'X-Goaiez-Signature';
	const HEADER_TIMESTAMP = 'X-Goaiez-Timestamp';
	const HEADER_NONCE     = 'X-Goaiez-Nonce';

	/**
	 * How far apart the two clocks may be.
	 *
	 * ⚠️ FIVE MINUTES IS A CHOICE ABOUT SOMEBODY ELSE'S SERVER CLOCK, not about
	 * our own. Shared hosting drifts, and a plugin that refused every request
	 * from a site four minutes fast would be indistinguishable from a plugin
	 * that was broken.
	 */
	const SKEW_SECONDS = 300;

	/**
	 * How long a used nonce is remembered. See the note above: 2×SKEW is the
	 * width of a signature's validity, and the extra minute is slack.
	 */
	const NONCE_TTL_SECONDS = 660;

	/** Minimum nonce length, in characters. */
	const NONCE_MIN_LENGTH = 16;

	/** Maximum nonce length, so the store cannot be filled with one request. */
	const NONCE_MAX_LENGTH = 128;

	/**
	 * The string a signature is computed over.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  The REST route WordPress matched.
	 * @param string $timestamp Unix seconds, as sent.
	 * @param string $nonce  The nonce, as sent.
	 * @param string $body   The raw request body.
	 * @return string
	 */
	public static function canonical_string( $method, $route, $timestamp, $nonce, $body ) {
		return implode(
			"\n",
			array(
				self::SCHEME,
				strtoupper( (string) $method ),
				(string) $route,
				(string) $timestamp,
				(string) $nonce,
				hash( 'sha256', (string) $body ),
			)
		);
	}

	/**
	 * Sign a canonical string. Used by the tests, and by nothing at runtime —
	 * this plugin only ever verifies.
	 *
	 * @param string $canonical The canonical string.
	 * @param string $secret    The shared secret.
	 * @return string
	 */
	public static function sign( $canonical, $secret ) {
		return hash_hmac( 'sha256', $canonical, (string) $secret );
	}

	/**
	 * Verify one request.
	 *
	 * ⚠️ THE ORDER IS DELIBERATE. Everything that can be decided without the
	 * secret is decided first, so a failure never depends on how long an HMAC
	 * took to compute; and the nonce is only spent once the signature is known
	 * to be ours.
	 *
	 * @param array    $headers    Header name => value. Names are compared
	 *                             case-insensitively, because HTTP header names
	 *                             are.
	 * @param string   $method     HTTP method.
	 * @param string   $route      The matched REST route.
	 * @param string   $body       The raw request body.
	 * @param string   $secret     This site's shared secret, or '' when the site
	 *                             is not paired.
	 * @param int      $now        Unix seconds.
	 * @param Goaiez_Nonce_Store $nonces Where used nonces are remembered.
	 * @return true|string True, or a machine-readable refusal code.
	 */
	public static function verify( array $headers, $method, $route, $body, $secret, $now, Goaiez_Nonce_Store $nonces ) {
		if ( ! is_string( $secret ) || '' === $secret ) {
			return 'not_paired';
		}

		$signature = self::header( $headers, self::HEADER_SIGNATURE );
		$timestamp = self::header( $headers, self::HEADER_TIMESTAMP );
		$nonce     = self::header( $headers, self::HEADER_NONCE );

		if ( null === $signature || null === $timestamp || null === $nonce ) {
			return 'missing_signature';
		}

		// A signature is 64 lower-case hex characters and nothing else. Checking
		// the shape here means `hash_equals()` below is always comparing two
		// strings of equal length, which is what it is for.
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return 'malformed_signature';
		}

		if ( 1 !== preg_match( '/^[0-9]{1,12}$/', $timestamp ) ) {
			return 'malformed_timestamp';
		}

		$length = strlen( $nonce );

		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $nonce ) || $length < self::NONCE_MIN_LENGTH || $length > self::NONCE_MAX_LENGTH ) {
			return 'malformed_nonce';
		}

		if ( abs( (int) $now - (int) $timestamp ) > self::SKEW_SECONDS ) {
			return 'stale_timestamp';
		}

		if ( $nonces->seen( $nonce ) ) {
			return 'replayed';
		}

		$expected = self::sign(
			self::canonical_string( $method, $route, $timestamp, $nonce, $body ),
			$secret
		);

		if ( ! hash_equals( $expected, $signature ) ) {
			return 'bad_signature';
		}

		$nonces->spend( $nonce, self::NONCE_TTL_SECONDS );

		return true;
	}

	/**
	 * One header, matched without regard to case.
	 *
	 * @param array  $headers Header name => value.
	 * @param string $name    The header wanted.
	 * @return string|null
	 */
	private static function header( array $headers, $name ) {
		// ⚠️ `-` AND `_` ARE THE SAME CHARACTER HERE, BECAUSE WORDPRESS SAYS SO.
		// `WP_REST_Request::canonicalize_header_name()` lower-cases a header name
		// and turns dashes into underscores, so the same header arrives as
		// `X-Goaiez-Signature` from a raw server array and `x_goaiez_signature`
		// from a REST request. A verifier that matched only one spelling would
		// refuse every real request or every test, depending which was written
		// first.
		$wanted = strtolower( str_replace( '_', '-', (string) $name ) );

		foreach ( $headers as $key => $value ) {
			if ( strtolower( str_replace( '_', '-', (string) $key ) ) === $wanted ) {
				if ( is_array( $value ) ) {
					$value = isset( $value[0] ) ? $value[0] : '';
				}

				$value = trim( (string) $value );

				return '' === $value ? null : $value;
			}
		}

		return null;
	}
}
