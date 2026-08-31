<?php
/**
 * Serving the IndexNow key file from the site root.
 *
 * @package Goaiez
 */

defined( 'ABSPATH' ) || exit;

/**
 * The one thing core's REST API cannot do, and the reason this plugin unblocks
 * IndexNow at all.
 *
 * ⛔ INDEXNOW BINDS A KEY TO THE DIRECTORY IT IS SERVED FROM. *"The location of
 * a key file determines the set of URLs that can be included with this key. A
 * key file located at `http://example.com/catalog/key12457EDd.txt` can include
 * any URLs starting with `http://example.com/catalog/` but cannot include URLs
 * starting with `http://example.com/help/`"*, and it *"is strongly recommended
 * that you use Option 1 and place your file key at the root directory"*
 * (`indexnow.org/documentation`, fetched 2026-08-19 for decision 5581 and again
 * 2026-08-20). A REST media upload lands under `/wp-content/uploads/`, so a key
 * put there can submit uploads and nothing else — which is why
 * `App\Services\Indexing\UnhostedIndexNowKeys` refuses every location today with
 * `KeyFileUnavailable`.
 *
 * ⛔ AND IT IS SERVED, NOT WRITTEN. Nothing here creates a file. WordPress
 * already answers any request the web server could not satisfy from disk, which
 * is how `robots.txt` works when there is no `robots.txt`, so a key file is a
 * response rather than an artefact. A plugin that wrote to the web root would be
 * a plugin with filesystem write access to a stranger's server, which is the
 * thing `19` §3.3 is written to prevent — and there is a lint asserting this
 * plugin never opens a file for writing at all.
 *
 * ⚠️ THE CONSEQUENCE IS A CASE THE PLATFORM MUST HANDLE: if a real file exists
 * at that path, or the host's rules do not route `.txt` through WordPress, this
 * never runs and the search engines get a 404 — which IndexNow answers with the
 * same 403 (*"key not found, file found but key not in the file"*) as a wrong
 * key. The status route reports whether the file is being served so the two can
 * be told apart, and it is staging item 19.
 */
class Goaiez_IndexNow {

	/**
	 * Serve `/{key}.txt` when that is what was asked for.
	 *
	 * ⚠️ THE COMPARISON IS AGAINST THE STORED KEY AND NEVER A PATTERN. Matching
	 * `/^[a-f0-9]+\.txt$/` and echoing back whatever was asked for would turn
	 * this into an oracle that confirms any key a stranger guesses, which is
	 * exactly the ownership proof IndexNow relies on.
	 *
	 * ⚠️ AND `hash_equals()` RATHER THAN `===`, because this is a secret
	 * comparison and the cost of using the right function is nothing.
	 *
	 * @param WP $wp The WordPress environment, passed by reference by the hook.
	 * @return void
	 */
	public static function maybe_serve_key_file( $wp ) {
		$key = Goaiez_Options::indexnow_key();

		if ( '' === $key ) {
			return;
		}

		$requested = isset( $wp->request ) ? (string) $wp->request : '';

		if ( '' === $requested || ! hash_equals( $key . '.txt', $requested ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
		}

		echo esc_html( $key );
		exit;
	}

	/**
	 * The vendor's own format, at its narrowest — the same rule as
	 * `App\Services\Indexing\IndexNowKey::isWellFormed()`.
	 *
	 * ⚠️ INDEXNOW'S OWN SENTENCE CONTRADICTS ITSELF (5688): a key *"should have
	 * a minimum of 8 and a maximum of 128 hexadecimal characters"* and, one
	 * clause later, *"can contain only … lowercase characters (a-z), uppercase
	 * characters (A-Z), numbers (0-9), and dashes (-)"*. Lower-case hex within
	 * the stated length satisfies both readings at once, which is the only
	 * choice here that cannot be wrong.
	 *
	 * @param string $key The candidate key.
	 * @return bool
	 */
	public static function is_well_formed( $key ) {
		return 1 === preg_match( '/^[a-f0-9]{8,128}$/', (string) $key );
	}

	/**
	 * Where the key file would be served from, for the status route.
	 *
	 * @return string
	 */
	public static function key_location() {
		$key = Goaiez_Options::indexnow_key();

		return '' === $key ? '' : home_url( '/' . $key . '.txt' );
	}
}
