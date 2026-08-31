<?php
/**
 * What is removed when the site owner deletes this plugin.
 *
 * @package Goaiez
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-goaiez-options.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-goaiez-log.php';

/**
 * ⛔ EVERYTHING THAT COULD LET US BACK IN GOES, AND THE RECORD OF WHAT WE DID
 * STAYS.
 *
 * The secret, the acting user, the IndexNow key, the sitemap announcement and
 * every live speed fix are removed: after this, a signed request from GO AI EZ
 * verifies against nothing, and nothing this plugin was doing to the site is
 * still being done.
 *
 * ⚠️ THE CHANGE LOG TABLE IS DELIBERATELY LEFT BEHIND, WHICH IS NOT THE USUAL
 * ADVICE. It is the owner's record of what was changed on their website and
 * their route back — and the changes themselves are in their pages, so deleting
 * the log destroys the map rather than the territory. There is a button on the
 * plugin's own screen for an owner who wants it gone, so this is a default they
 * can override rather than a decision made for them.
 *
 * ⚠️ ON MULTISITE THIS SWEEPS UP TO 500 SITES. A network larger than that keeps
 * options on the rest, which is a bounded, visible shortcoming rather than a
 * query that times out halfway through and leaves an unknown subset done.
 */
function goaiez_uninstall_this_site() {
	foreach ( Goaiez_Options::all_keys() as $key ) {
		delete_option( $key );
	}

	delete_transient( 'goaiez_inventory_wanted' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 500 ) ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		goaiez_uninstall_this_site();
		restore_current_blog();
	}
} else {
	goaiez_uninstall_this_site();
}
