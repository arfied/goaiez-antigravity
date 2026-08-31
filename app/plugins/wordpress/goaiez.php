<?php

/**
 * Plugin Name:       GO AI EZ
 * Plugin URI:        https://goaiez.com/wordpress
 * Description:       Lets GO AI EZ make the website changes you asked for, keeps a log of every one of them on your own site, and gives you a one-click undo that does not need us.
 * Version:           0.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            GO AI EZ
 * Author URI:        https://goaiez.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       goaiez
 * Update URI:        false
 */

/*
 * ⛔ THIS FILE RUNS INSIDE A STRANGER'S WEBSITE. Read
 * `plugins/wordpress/README.md` in the GO AI EZ repository before changing
 * anything here, and `docs/19-PIXEL-INTEGRATION-AND-ACTUATION.md` §3.3, which
 * opens "do not shortcut".
 *
 * ## The header fields above, against the vendor's own list
 *
 * Every field is from the Plugin Handbook's header-requirements page
 * (`developer.wordpress.org/plugins/plugin-basics/header-requirements/`, page
 * last updated 2026-03-11, fetched 2026-08-20). "At a minimum, a header comment
 * must contain the Plugin Name."
 *
 * ⛔ `Update URI: false` IS A MECHANISM AND NOT A PLACEHOLDER. `19` §3.3 and
 * `CLAUDE.md` both forbid "auto-update of plugin code from the platform". The
 * absence of update code is one half; this header is the other, and it closes a
 * hole the absence does not: a plugin distributed as a zip whose folder name
 * collides with an existing wordpress.org slug is otherwise overwritten by
 * *that* plugin's next release. "`Update URI: false` also works, and unless
 * there is some code handling the `false` hostname … the plugin will not be
 * updated"
 * (`make.wordpress.org/core/2021/06/29/introducing-update-uri-plugin-header-in-wordpress-5-8/`,
 * published 2021-06-29, fetched 2026-08-20).
 *
 * ⚠️ SO REMOVING THIS LINE IS A STEP OF THE wordpress.org SUBMISSION, NOT A
 * TIDY-UP. While it is here the plugin can never be updated by anybody,
 * including wordpress.org. Once the listing exists the value becomes
 * `https://wordpress.org/plugins/{slug}/` or the line goes — and until then,
 * shipping without it would mean the first plugin on wordpress.org with a
 * similar slug can push its code into our customers' sites.
 *
 * ⚠️ `Requires at least: 5.6` IS THE APPLICATION PASSWORDS FLOOR AND IT IS NOT
 * ARBITRARY. The platform's other route into a site is core's Application
 * Passwords, "As of 5.6" (`developer.wordpress.org/rest-api/using-the-rest-api/authentication/`,
 * decision 5584). 5.5 would also have been defensible — it is the sitemaps and
 * lazy-loading floor this plugin reads — but a site below 5.6 cannot be
 * connected by any path we ship, so the plugin would be inert there.
 */

defined('ABSPATH') || exit;

define('GOAIEZ_VERSION', '0.1.0');
define('GOAIEZ_PLUGIN_FILE', __FILE__);
define('GOAIEZ_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-signature.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-capabilities.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-options.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-log.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-writer.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-speed.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-indexnow.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-robots.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-rest.php';
require_once GOAIEZ_PLUGIN_DIR.'includes/class-goaiez-admin.php';

register_activation_hook(__FILE__, ['Goaiez_Log', 'install']);
register_deactivation_hook(__FILE__, ['Goaiez_Options', 'on_deactivate']);

add_action('rest_api_init', ['Goaiez_Rest', 'register_routes']);
add_action('parse_request', ['Goaiez_IndexNow', 'maybe_serve_key_file']);
add_filter('robots_txt', ['Goaiez_Robots', 'announce_sitemap'], PHP_INT_MAX - 1, 2);
add_action('admin_menu', ['Goaiez_Admin', 'register_page']);
add_action('admin_post_goaiez_action', ['Goaiez_Admin', 'handle_post']);

Goaiez_Speed::boot();
