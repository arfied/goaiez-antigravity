<?php
/**
 * §19.7's least-privilege gate, enforced from inside WordPress.
 *
 * @package Goaiez
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which WordPress user this plugin is allowed to act as.
 *
 * ⛔ THE GATE IS ABOUT A USER, NEVER ABOUT A CREDENTIAL TYPE — decision 5582.
 * An Application Password carries the capabilities of the user it belongs to and
 * is not a scope; the same is true of this plugin, whose secret would otherwise
 * be a key to whatever WordPress happens to let it do. So the plugin acts as a
 * WordPress user the site owner picks, and refuses to act as one who holds any
 * of the capabilities `29` §19.7 names: *"the credential cannot install plugins,
 * edit users, or execute code."*
 *
 * ⛔ AND IT IS RE-ASKED ON EVERY WRITE, NOT ONLY WHEN THE USER IS PICKED.
 * Decision 5759 lists "a credential that gains capabilities inside WordPress
 * between `health()` and the write" among the things nothing yet catches. From
 * in here it is catchable, because the check and the write are microseconds
 * apart in the same process, and this is the only place in the whole system
 * where that is true.
 *
 * ⚠️ THE TWO LISTS BELOW ARE COPIES OF `App\Services\Actuation\WordPress\
 * LeastPrivilege::FORBIDDEN` AND `::REQUIRED`, AND A COPY IS A THING THAT
 * DRIFTS. They cannot be shared — that class is Laravel and this file runs on
 * somebody else's server with no autoloader of ours — so the platform's test
 * suite parses this file and fails the build if either list stops matching.
 * Where a copy is unavoidable, the machine keeps them equal.
 *
 * ⚠️ `unfiltered_html` IS DELIBERATELY ABSENT, exactly as it is there (5587): a
 * stock single-site Editor has it, so forbidding it would refuse the very role
 * the owner is asked to use, and a gate that refuses its own recommended answer
 * gets widened until it catches nothing.
 */
class Goaiez_Capabilities {

	/**
	 * The capabilities §19.7's three prohibitions name. Grouped by prohibition,
	 * not sorted, so the next person to edit the list has to answer "which of
	 * the three is this".
	 *
	 * @var string[]
	 */
	const FORBIDDEN = array(
		// 1. Install or edit code.
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'delete_plugins',
		'update_plugins',
		'upload_plugins',
		'manage_network_plugins',
		'install_themes',
		'edit_themes',
		'delete_themes',
		'update_themes',
		'upload_themes',
		'switch_themes',
		'manage_network_themes',
		'edit_files',
		'update_core',

		// 2. Edit users.
		'edit_users',
		'create_users',
		'delete_users',
		'promote_users',
		'add_users',
		'remove_users',
		'manage_network_users',

		// 3. Own the site outright, which is execute-code by another route.
		'manage_options',
		'manage_network',
		'manage_network_options',
		'manage_sites',
		'create_sites',
		'delete_sites',
		'delete_site',
		'setup_network',
		'upgrade_network',
	);

	/**
	 * What the acting user has to be able to do, or picking them buys nothing.
	 *
	 * @var string[]
	 */
	const REQUIRED = array(
		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'edit_pages',
		'edit_others_pages',
		'edit_published_pages',
	);

	/**
	 * The forbidden capabilities a map actually grants.
	 *
	 * ⚠️ A `false` VALUE IS NOT A GRANT. `allcaps` is a map and a role that has
	 * had a capability removed carries the key with `false`, so reading it with
	 * `array_key_exists()` refuses a user WordPress has already denied.
	 *
	 * @param array $capabilities Capability name => boolean-ish.
	 * @return string[]
	 */
	public static function excessive( array $capabilities ) {
		$found = array();

		foreach ( self::FORBIDDEN as $cap ) {
			if ( ! empty( $capabilities[ $cap ] ) ) {
				$found[] = $cap;
			}
		}

		return $found;
	}

	/**
	 * The capabilities the work needs and this map does not grant.
	 *
	 * @param array $capabilities Capability name => boolean-ish.
	 * @return string[]
	 */
	public static function missing( array $capabilities ) {
		$found = array();

		foreach ( self::REQUIRED as $cap ) {
			if ( empty( $capabilities[ $cap ] ) ) {
				$found[] = $cap;
			}
		}

		return $found;
	}

	/**
	 * The gate's answer for a capability map, or null when the user is right.
	 *
	 * ⚠️ TOO POWERFUL IS ANSWERED FIRST AND THE ORDER IS LOAD-BEARING, exactly
	 * as it is platform-side: an Administrator satisfies every required
	 * capability, so answering `too_weak` first would still refuse them while
	 * telling the owner to grant *more*.
	 *
	 * @param array $capabilities Capability name => boolean-ish.
	 * @return string|null 'too_powerful', 'too_weak', or null.
	 */
	public static function refusal_for( array $capabilities ) {
		if ( array() !== self::excessive( $capabilities ) ) {
			return 'too_powerful';
		}

		if ( array() !== self::missing( $capabilities ) ) {
			return 'too_weak';
		}

		return null;
	}

	/**
	 * The same question about a live WordPress user.
	 *
	 * ⛔ A SUPER ADMIN'S CAPABILITY MAP READS EXACTLY LIKE AN EDITOR'S (5585),
	 * because super-admin status lives in a network option rather than in a role
	 * and `WP_User::has_cap()` short-circuits for it. Platform-side that hole is
	 * closed by a second, behavioural probe against `GET /wp/v2/plugins`; in
	 * here it is closed properly, because `is_super_admin()` is the actual
	 * answer and is one function call away.
	 *
	 * @param int $user_id WordPress user id.
	 * @return string|null A refusal code, or null when the user may act.
	 */
	public static function refusal_for_user( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return 'no_actor';
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return 'no_actor';
		}

		if ( is_multisite() && is_super_admin( $user_id ) ) {
			return 'too_powerful';
		}

		return self::refusal_for( (array) $user->allcaps );
	}

	/**
	 * Users the site owner may choose from.
	 *
	 * ⚠️ THE PICKER ONLY OFFERS USERS WHO PASS, WHICH IS NOT THE SAME AS THE
	 * GATE. The gate is {@see self::refusal_for_user()} and it runs again on
	 * every single write; this is a convenience so that an owner is not asked to
	 * guess. A user who passes today and is promoted tomorrow is refused at the
	 * next write, not at the next page load of this screen.
	 *
	 * @return WP_User[]
	 */
	public static function eligible_users() {
		$eligible = array();

		foreach ( get_users( array( 'number' => 200 ) ) as $user ) {
			if ( null === self::refusal_for_user( $user->ID ) ) {
				$eligible[] = $user;
			}
		}

		return $eligible;
	}
}
