<?php
/**
 * Writing to, creating and undoing a page.
 *
 * @package Goaiez
 */

defined( 'ABSPATH' ) || exit;

/**
 * The only code in this plugin that changes a page on this website.
 *
 * ⛔ EVERY WRITE PASSES FOUR GATES, IN THIS ORDER, AND NONE OF THEM IS THE
 * SIGNATURE. A valid signature says the request came from GO AI EZ; it says
 * nothing about whether GO AI EZ may do this. The four are: the kill switch
 * ({@see Goaiez_Options::write_access()}); the acting user still passing §19.7's
 * least-privilege gate ({@see Goaiez_Capabilities::refusal_for_user()}); that
 * user being able to edit *this particular post* (`user_can( $actor,
 * 'edit_post', $id )`, the meta capability, which resolves per post rather than
 * per role); and the prior state having been recorded.
 *
 * ⛔ THE SECOND OF THOSE IS ASKED AGAIN ON EVERY WRITE AND THAT IS THE POINT.
 * Decision 5759 lists "a credential that gains capabilities inside WordPress
 * between `health()` and the write" among the things nothing catches. From here
 * the check and the write are the same request.
 *
 * ## Two facts about WordPress that a REST client outside cannot have
 *
 * ✅ **THE FRONT PAGE IS ADDRESSABLE FROM IN HERE.** Decision 5592 records that
 * core REST has no permalink lookup, so a URL resolves by its last path segment
 * as a slug and the site root has none — and that the documented fix reads
 * `page_on_front` from `/wp/v2/settings`, which needs `manage_options`, which
 * §19.7's gate forbids. In here `get_option( 'page_on_front' )` is a function
 * call and needs no capability at all, so the collision 5592 describes does not
 * exist on this path.
 *
 * ✅ **AND SO IS EVERY OTHER URL, WITHOUT GUESSING.** `url_to_postid()` is
 * WordPress's own resolver, running the site's own rewrite rules, so a page and
 * a post sharing a slug (5593), a nested address (5773) and a plain-permalink
 * `?page_id=` URL all resolve to the post they actually are rather than to
 * whichever collection answered first.
 *
 * ⚠️ WHAT DOES NOT CHANGE IS THE READ-BACK. Decision 5588: a write can return
 * success and not have happened, because `kses` strips disallowed markup on save
 * for any user without `unfiltered_html` and any optimisation plugin can filter
 * `content_save_pre`. Running in-process removes the network from between the
 * write and the check; it does not remove the filters. So every write is read
 * back out of the database and a value that did not take is a failure that puts
 * the prior values back.
 */
class Goaiez_Writer {

	/**
	 * The fields a change set may name, mapped to their post columns.
	 *
	 * ⚠️ IT IS THE SAME THREE AS `WordPressRestClient::WRITABLE_FIELDS`, AND
	 * THAT IS A CHOICE RATHER THAN A LIMIT. A plugin can write far more than
	 * core REST can — post meta, terms, options, template parts — and every one
	 * of those is a new way to break somebody's website. The vocabulary widens
	 * when a slice needs it and argues for it, not because it could.
	 *
	 * ⛔ `meta_description` IS STILL ABSENT AND DECISION 5591 IS WHY. WordPress
	 * has no meta description; it is an SEO plugin's registered post meta, under
	 * that plugin's own private key. Writing another plugin's private meta is a
	 * thing to do after reading that plugin's documentation, which 5591 names as
	 * owed to a later slice. What this plugin does instead is **report** which
	 * SEO plugin is active, so the platform can stop guessing.
	 *
	 * @var array
	 */
	const FIELDS = array(
		'title'   => 'post_title',
		'content' => 'post_content',
		'excerpt' => 'post_excerpt',
	);

	/**
	 * The sentinel a creation's prior state carries — decision 5770's
	 * "_page_state": "absent", written the same way here so the two logs agree.
	 */
	const ABSENT = array( '_page_state' => 'absent' );

	/**
	 * Resolve a URL on this site to a post id.
	 *
	 * @param string $url Absolute URL.
	 * @return int|WP_Error Post id, 0 when nothing is there, or an error.
	 */
	public static function locate( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return new WP_Error( 'goaiez_bad_url', 'No address given.' );
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );

		// ⚠️ `www.` IS A DIFFERENT HOST, BECAUSE WORDPRESS TREATS IT AS ONE
		// (5593). A site canonicalising one to the other will have redirected
		// before anything reached us.
		if ( ! is_string( $host ) || ! is_string( $home ) || 0 !== strcasecmp( $host, $home ) ) {
			return new WP_Error( 'goaiez_foreign_host', 'That address is not on this site.' );
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$root = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( untrailingslashit( $path ) === untrailingslashit( $root ) && 'page' === get_option( 'show_on_front' ) ) {
			return (int) get_option( 'page_on_front' );
		}

		return (int) url_to_postid( $url );
	}

	/**
	 * Read the current values of the named fields on a page.
	 *
	 * ⚠️ THE STORED VALUE, NEVER THE RENDERED ONE. A snapshot taken after the
	 * site's content filters is a different page from the one in the database,
	 * so writing it back would put something else there and rule 32's promise
	 * would restore the wrong thing. Staging item 10.
	 *
	 * @param int   $post_id Post id.
	 * @param array $fields  Field names.
	 * @return array|WP_Error
	 */
	public static function snapshot( $post_id, array $fields ) {
		$post = get_post( (int) $post_id );

		if ( ! $post ) {
			return new WP_Error( 'goaiez_absent', 'There is no page at that address.' );
		}

		$snapshot = array();

		foreach ( $fields as $field ) {
			if ( isset( self::FIELDS[ $field ] ) ) {
				$column              = self::FIELDS[ $field ];
				$snapshot[ $field ] = (string) $post->$column;
			}
		}

		return $snapshot;
	}

	/**
	 * Apply a change set to an existing page.
	 *
	 * @param array $change change_id, url, before, after, summary.
	 * @return array|WP_Error
	 */
	public static function write( array $change ) {
		$gate = self::gate();

		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$actor = (int) $gate;

		// ⛔ A CHANGE ALREADY IN THIS SITE'S LOG IS ALREADY DONE, AND ASKING
		// FIRST IS WHAT MAKES A RETRY SAFE. The signature layer permits a
		// genuine retry — a new nonce and a fresh timestamp — so without this a
		// second delivery of the same change set would re-apply it and then fail
		// to log it, leaving a website edited with no row and therefore no undo.
		// `change_id` is unique in the table, so the log is the idempotency key
		// rather than a second one being invented.
		$already = Goaiez_Log::find( isset( $change['change_id'] ) ? $change['change_id'] : '' );

		if ( null !== $already ) {
			return array(
				'post_id' => (int) $already['object_id'],
				'url'     => (string) $already['url'],
				'applied' => isset( $change['after'] ) && is_array( $change['after'] ) ? $change['after'] : array(),
				'already' => true,
			);
		}

		$post_id = self::locate( isset( $change['url'] ) ? $change['url'] : '' );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $post_id <= 0 ) {
			return new WP_Error( 'goaiez_absent', 'There is no page at that address.' );
		}

		if ( ! user_can( $actor, 'edit_post', $post_id ) ) {
			return new WP_Error( 'goaiez_cannot_edit_this_page', 'The user you chose cannot edit that page.' );
		}

		$after = isset( $change['after'] ) && is_array( $change['after'] ) ? $change['after'] : array();

		$unknown = array_diff( array_keys( $after ), array_keys( self::FIELDS ) );

		// ⛔ REFUSED WHOLE, BY NAME, NEVER PARTLY APPLIED (5591, 5532). Writing
		// two fields of three records three in the change set and puts two on
		// the page, and the rollback would then restore a state that never
		// existed.
		if ( array() !== $unknown ) {
			return new WP_Error( 'goaiez_field_not_writable', 'This plugin cannot write: ' . implode( ', ', $unknown ) );
		}

		if ( array() === $after ) {
			return new WP_Error( 'goaiez_empty_change', 'That change set names nothing to write.' );
		}

		$before = self::snapshot( $post_id, array_keys( $after ) );

		if ( is_wp_error( $before ) ) {
			return $before;
		}

		$applied = self::put( $post_id, $after, $actor );

		if ( is_wp_error( $applied ) ) {
			// ⛔ THE ABORT IS A COMPENSATING WRITE BECAUSE WORDPRESS HAS NO
			// TRANSACTION TO GIVE US (5589), AND A RESTORE THAT ITSELF FAILS IS
			// ITS OWN OUTCOME rather than folded into the first — the two leave
			// the owner's page in different states and only one needs a person.
			$restored = self::put( $post_id, $before, $actor );

			if ( is_wp_error( $restored ) ) {
				return new WP_Error(
					'goaiez_write_failed_and_not_restored',
					'The change did not take and the previous version could not be put back.'
				);
			}

			return $applied;
		}

		$logged = Goaiez_Log::record(
			array(
				'change_id'     => isset( $change['change_id'] ) ? $change['change_id'] : '',
				'kind'          => 'write',
				'object_type'   => 'post',
				'object_id'     => $post_id,
				'url'           => isset( $change['url'] ) ? $change['url'] : '',
				'summary'       => isset( $change['summary'] ) ? $change['summary'] : 'Changed this page.',
				'before'        => $before,
				'after'         => $after,
				'actor_user_id' => $actor,
			)
		);

		// ⛔ AN UNLOGGED CHANGE IS A CHANGE WITH NO UNDO, WHICH IS RULE 32
		// FAILING QUIETLY. So a log that would not write puts the page back and
		// reports failure, rather than leaving an edit on somebody's website
		// that neither they nor we can find again.
		if ( ! $logged ) {
			$restored = self::put( $post_id, $before, $actor );

			return new WP_Error(
				is_wp_error( $restored ) ? 'goaiez_write_failed_and_not_restored' : 'goaiez_not_recorded',
				'The change could not be written down on this site, so it was not kept.'
			);
		}

		return array(
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'applied' => $after,
		);
	}

	/**
	 * Create the page a change set describes, at the address it names.
	 *
	 * ⛔ IT MUST LAND AT THAT ADDRESS OR NOT EXIST (5773). WordPress chooses a
	 * permalink from the slug, the post type, the permalink structure and any
	 * page already holding that slug — `wp_unique_post_slug()` silently appends
	 * `-2`. The named URL is what was snapshotted, what a rollback addresses,
	 * what measurement reads and what was announced to search engines, so a page
	 * at a different address is a stray page on somebody's website rather than a
	 * partly good outcome.
	 *
	 * @param array $change change_id, url, after, summary.
	 * @return array|WP_Error
	 */
	public static function create( array $change ) {
		$gate = self::gate();

		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$actor = (int) $gate;

		if ( ! user_can( $actor, 'publish_pages' ) ) {
			return new WP_Error( 'goaiez_cannot_publish', 'The user you chose cannot publish pages.' );
		}

		$already = Goaiez_Log::find( isset( $change['change_id'] ) ? $change['change_id'] : '' );

		if ( null !== $already ) {
			return array(
				'post_id' => (int) $already['object_id'],
				'url'     => (string) $already['url'],
				'created' => true,
				'already' => true,
			);
		}

		$url = isset( $change['url'] ) ? (string) $change['url'] : '';

		$existing = self::locate( $url );

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		if ( $existing > 0 ) {
			return new WP_Error( 'goaiez_already_there', 'There is already a page at that address.' );
		}

		$after = isset( $change['after'] ) && is_array( $change['after'] ) ? $change['after'] : array();

		if ( ! isset( $after['title'] ) || ! isset( $after['content'] ) ) {
			return new WP_Error( 'goaiez_page_fields_missing', 'A page is its title and its body.' );
		}

		$path     = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$segments = '' === $path ? array() : explode( '/', $path );

		// ⛔ A NESTED ADDRESS IS REFUSED BY NAME (5773). Core wants a parent id,
		// and resolving one means looking up the page above and hoping it is the
		// right one — 5593's ambiguity with a page creation on the end.
		if ( 1 !== count( $segments ) ) {
			return new WP_Error( 'goaiez_nested_address_not_creatable', 'This plugin only creates pages one level below the home page.' );
		}

		$previous = self::become( $actor );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $segments[0],
				'post_title'   => (string) $after['title'],
				'post_content' => (string) $after['content'],
				'post_excerpt' => isset( $after['excerpt'] ) ? (string) $after['excerpt'] : '',
				'post_author'  => $actor,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::restore_user( $previous );

			return $post_id;
		}

		$landed = get_permalink( $post_id );

		// ⛔ A CREATION IS READ BACK FOR THE SAME REASON A WRITE IS (5588), AND
		// THE FIRST DRAFT OF THIS METHOD DID NOT. `kses` strips disallowed
		// markup on save for any user without `unfiltered_html` — which a
		// multisite Editor is, and that is the role §19.7's gate asks for — so a
		// page could be published with its body quietly rewritten and reported
		// as a success.
		$stored = self::mismatched_fields( $post_id, $after );

		if ( array() !== $stored ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			self::restore_user( $previous );

			return new WP_Error(
				'goaiez_write_did_not_take',
				'This site changed "' . implode( '", "', $stored ) . '" as the page was saved, so it was not published.'
			);
		}

		if ( ! self::same_address( $landed, $url ) ) {
			// The compensating unpublish. Never a delete: this platform does not
			// destroy anything on a customer's website (5771).
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			self::restore_user( $previous );

			return new WP_Error(
				'goaiez_landed_elsewhere',
				'The new page would have appeared at a different address, so it was not published.'
			);
		}

		self::restore_user( $previous );

		$logged = Goaiez_Log::record(
			array(
				'change_id'     => isset( $change['change_id'] ) ? $change['change_id'] : '',
				'kind'          => 'create',
				'object_type'   => 'post',
				'object_id'     => $post_id,
				'url'           => $url,
				'summary'       => isset( $change['summary'] ) ? $change['summary'] : 'Added this page.',
				'before'        => self::ABSENT,
				'after'         => $after,
				'actor_user_id' => $actor,
			)
		);

		if ( ! $logged ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );

			return new WP_Error(
				'goaiez_not_recorded',
				'The new page could not be written down on this site, so it was not published.'
			);
		}

		return array(
			'post_id' => (int) $post_id,
			'url'     => $landed,
			'created' => true,
		);
	}

	/**
	 * Undo one logged change.
	 *
	 * ⛔ THIS IS THE METHOD THE OWNER'S OWN BUTTON CALLS AND IT NEEDS NOTHING
	 * FROM US. `$by` is who asked, for the log; the work is identical either
	 * way, deliberately, so that the path an owner uses is the path we test.
	 *
	 * @param string $change_id The platform's change id.
	 * @param string $by        'owner' or 'platform'.
	 * @return array|WP_Error
	 */
	public static function revert( $change_id, $by ) {
		$row = Goaiez_Log::find( $change_id );

		if ( null === $row ) {
			return new WP_Error( 'goaiez_unknown_change', 'That change is not in this site\'s log.' );
		}

		if ( ! empty( $row['reverted_at'] ) ) {
			return array( 'already' => true, 'change_id' => (string) $change_id );
		}

		$actor = self::actor_for_revert( $by );

		if ( is_wp_error( $actor ) ) {
			return $actor;
		}

		$post_id = (int) $row['object_id'];
		$before  = Goaiez_Log::before( $row );

		if ( ! user_can( $actor, 'edit_post', $post_id ) ) {
			return new WP_Error( 'goaiez_cannot_edit_this_page', 'That user cannot edit that page.' );
		}

		if ( 'create' === $row['kind'] ) {
			// ⛔ AN UNPUBLISH, NEVER A DELETE (5771). The visitor sees what they
			// saw before, and every word stays in the owner's own WordPress
			// under Pages, where they can put it back if they disagree with us.
			$post = get_post( $post_id );

			if ( $post && 'draft' !== $post->post_status ) {
				$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ), true );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			Goaiez_Log::mark_reverted( $change_id, $by );

			return array( 'change_id' => (string) $change_id, 'unpublished' => true );
		}

		$restored = self::put( $post_id, $before, $actor );

		if ( is_wp_error( $restored ) ) {
			return $restored;
		}

		Goaiez_Log::mark_reverted( $change_id, $by );

		return array( 'change_id' => (string) $change_id, 'restored' => array_keys( $before ) );
	}

	/**
	 * The kill switch and the least-privilege gate, asked together.
	 *
	 * @return int|WP_Error The acting user id, or the refusal.
	 */
	public static function gate() {
		if ( ! Goaiez_Options::write_access() ) {
			return new WP_Error( 'goaiez_write_access_off', 'Write access is switched off on this site.' );
		}

		$actor   = Goaiez_Options::actor();
		$refusal = Goaiez_Capabilities::refusal_for_user( $actor );

		if ( null !== $refusal ) {
			return new WP_Error( 'goaiez_' . $refusal, 'The user this plugin is set to act as cannot be used: ' . $refusal . '.' );
		}

		return $actor;
	}

	/**
	 * Who an undo is performed as.
	 *
	 * ⚠️ AN OWNER PRESSING UNDO IN THEIR OWN ADMIN IS THEMSELVES, NOT US, AND
	 * THE KILL SWITCH DOES NOT APPLY TO THEM. Refusing an owner's undo because
	 * they had already severed *our* write access would be this plugin holding a
	 * change hostage to a switch built to protect them from it.
	 *
	 * @param string $by 'owner' or 'platform'.
	 * @return int|WP_Error
	 */
	private static function actor_for_revert( $by ) {
		if ( 'owner' === $by ) {
			return get_current_user_id();
		}

		return self::gate();
	}

	/**
	 * Write field values to a post and prove they took.
	 *
	 * @param int   $post_id Post id.
	 * @param array $values  Field name => value.
	 * @param int   $actor   Acting user id.
	 * @return true|WP_Error
	 */
	private static function put( $post_id, array $values, $actor ) {
		$update = array( 'ID' => (int) $post_id );

		foreach ( $values as $field => $value ) {
			if ( isset( self::FIELDS[ $field ] ) ) {
				$update[ self::FIELDS[ $field ] ] = (string) $value;
			}
		}

		if ( 1 === count( $update ) ) {
			return new WP_Error( 'goaiez_empty_change', 'Nothing to write.' );
		}

		$previous = self::become( $actor );

		$result = wp_update_post( $update, true );

		self::restore_user( $previous );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$mismatched = self::mismatched_fields( $post_id, $values );

		if ( array() !== $mismatched ) {
			return new WP_Error(
				'goaiez_write_did_not_take',
				'This site changed "' . implode( '", "', $mismatched ) . '" as it was saved, so the change was not kept.'
			);
		}

		return true;
	}

	/**
	 * The fields whose stored value is not the value we asked for.
	 *
	 * ⚠️ ONE COMPARISON, TWO CALLERS. A creation and an update are checked by
	 * the same code so they cannot come to disagree about what "it took" means.
	 *
	 * @param int   $post_id Post id.
	 * @param array $values  Field name => value.
	 * @return string[]
	 */
	private static function mismatched_fields( $post_id, array $values ) {
		clean_post_cache( (int) $post_id );

		$stored = get_post( (int) $post_id );

		if ( ! $stored ) {
			return array_keys( $values );
		}

		$mismatched = array();

		foreach ( $values as $field => $value ) {
			if ( ! isset( self::FIELDS[ $field ] ) ) {
				continue;
			}

			$column = self::FIELDS[ $field ];

			if ( ! self::matches( (string) $stored->$column, (string) $value ) ) {
				$mismatched[] = (string) $field;
			}
		}

		return $mismatched;
	}

	/**
	 * Whether a stored value is the value we asked for.
	 *
	 * ⚠️ LINE ENDINGS ARE NORMALISED AND NOTHING ELSE IS, DELIBERATELY (5590).
	 * Softening this further turns it back into a shape check, and a shape check
	 * cannot tell a page that was written from a page that was rewritten. If a
	 * real install shows a normalisation we do not model, the answer is to model
	 * it, never to loosen this.
	 *
	 * @param string $stored What is in the database now.
	 * @param string $wanted What was asked for.
	 * @return bool
	 */
	public static function matches( $stored, $wanted ) {
		return self::normalise_endings( $stored ) === self::normalise_endings( $wanted );
	}

	/**
	 * The one normalisation {@see self::matches()} performs.
	 *
	 * ⚠️ A NAMED METHOD RATHER THAN A CLOSURE, AND THE LINT IS WHY. The plugin
	 * may not dispatch through a value — `$callback( … )` is how request input
	 * becomes a function name — and the first draft of this class called two
	 * closures that way. `tests/Feature/Architecture/PluginSourceTest.php`
	 * caught it on the run it was written on.
	 *
	 * @param string $value The value.
	 * @return string
	 */
	private static function normalise_endings( $value ) {
		return str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
	}

	/**
	 * Whether two addresses name the same page.
	 *
	 * @param string $a One address.
	 * @param string $b The other.
	 * @return bool
	 */
	public static function same_address( $a, $b ) {
		return self::comparable_address( $a ) === self::comparable_address( $b );
	}

	/**
	 * An address reduced to the parts that decide whether it is the same page.
	 *
	 * ⚠️ QUERY, FRAGMENT AND A TRAILING SLASH ARE IGNORED AND `www.` IS NOT,
	 * which is 5593's rule: WordPress treats `www.` as a different host, so a
	 * site that canonicalises one to the other has already redirected.
	 *
	 * @param string $url The address.
	 * @return string
	 */
	private static function comparable_address( $url ) {
		$parts = wp_parse_url( (string) $url );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';

		return $host . '/' . trim( $path, '/' );
	}

	/**
	 * Act as the chosen user for the next few lines.
	 *
	 * ⛔ `kses_init()` IS CALLED EXPLICITLY AND THAT IS NOT BELT-AND-BRACES.
	 * Core's kses filters are decided once, at `init`, from whoever the current
	 * user was then — which on an unauthenticated REST request is nobody. Left
	 * alone, content would be filtered as if by an untrusted visitor whatever
	 * the acting user's capabilities are, and the read-back would then report a
	 * failure the site never really had. `kses_init()` is core's own re-decider:
	 * *"kses_remove_filters(); if ( ! current_user_can( 'unfiltered_html' ) ) {
	 * kses_init_filters(); }"*
	 * (`developer.wordpress.org/reference/functions/kses_init/`, fetched
	 * 2026-08-20).
	 *
	 * @param int $actor User id to become.
	 * @return int The user id to go back to.
	 */
	private static function become( $actor ) {
		$previous = get_current_user_id();

		wp_set_current_user( (int) $actor );
		kses_init();

		return (int) $previous;
	}

	/**
	 * Go back to whoever we were.
	 *
	 * @param int $previous The user id to restore.
	 * @return void
	 */
	private static function restore_user( $previous ) {
		wp_set_current_user( (int) $previous );
		kses_init();
	}
}
