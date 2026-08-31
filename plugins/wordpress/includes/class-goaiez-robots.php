<?php
/**
 * The sitemap line in robots.txt — the exception, not the rule.
 *
 * @package Goaiez
 */

defined( 'ABSPATH' ) || exit;

/**
 * Announcing this site's sitemap to search engines, on the minority of sites
 * that are not already doing it.
 *
 * ⛔ THE PLAN SAID THIS WAS WORK OWED ON EVERY WORDPRESS SITE AND IT IS NOT.
 * `BUILD-PLAN` §2.11.3's E row reads *"ensure WP's native sitemap is named in
 * robots.txt via the plugin"*; decision 5683 found core has done it since 5.5.
 * Verified again from core's own source on 2026-08-20:
 *
 *     public function add_robots( $output, $is_public ) {
 *         if ( $is_public ) {
 *             $output .= "\nSitemap: " . esc_url( $this->index->get_index_url() ) . "\n";
 *         }
 *         return $output;
 *     }
 *
 * (`developer.wordpress.org/reference/classes/wp_sitemaps/add_robots/`,
 * introduced 5.5.0, page last modified 2026-08-20.) So the default here is
 * **off**, and switching it on by default would put a second, duplicate Sitemap
 * line on most WordPress sites on earth.
 *
 * ## Three ways a site ends up without the line, and only one of them is ours
 *
 * ⛔ **1. THE OWNER ASKED SEARCH ENGINES TO STAY AWAY, AND THIS PLUGIN OBEYS
 * THAT RATHER THAN WORKING AROUND IT.** `add_robots()` above adds nothing when
 * the site is not public, and *"if you update the Site Visibility settings in
 * WordPress admin to discourage search engines from indexing your site, sitemaps
 * will be disabled"*
 * (`make.wordpress.org/core/2020/07/22/new-xml-sitemaps-functionality-in-wordpress-5-5/`,
 * published 2020-07-22, fetched 2026-08-20). **5683 names this as the exception
 * F2 owes and it is the half F2 must refuse**: announcing a sitemap on a site
 * whose owner has ticked *discourage search engines* is this platform
 * overriding a customer's own stated instruction about their own website, on
 * their website, silently. The status route reports the setting so somebody can
 * ask them; nothing here changes it.
 *
 * ⚠️ **2. AN SEO PLUGIN TURNED CORE'S SITEMAPS OFF AND SERVES ITS OWN.** Then
 * core's `/wp-sitemap.xml` is a 404 — *"rewrite rules are still in place to
 * ensure a 404 is returned"* — and announcing it would hand a search engine a
 * dead address. So this checks that core's sitemaps are actually enabled before
 * naming core's index, and reports the fact rather than guessing at the other
 * plugin's URL.
 *
 * ✅ **3. SOMETHING REPLACED THE LINE THROUGH THIS SAME FILTER.** That is the
 * case this class is for, and it works because this filter runs last.
 *
 * ⛔ AND A FOURTH SITUATION IS UNREACHABLE FROM ANY PLUGIN, WHICH THE PLAN DOES
 * NOT ANTICIPATE. WordPress serves a *virtual* robots.txt only when no real file
 * exists in the web root; where one does, the web server returns it and
 * `do_robots()` — and therefore the `robots_txt` filter — never runs at all. On
 * those sites this plugin can do nothing, and the honest answer is to say so on
 * the status route rather than to start writing files into somebody's web root.
 */
class Goaiez_Robots {

	/**
	 * Add the sitemap line, if every condition holds.
	 *
	 * ⚠️ HOOKED AT `PHP_INT_MAX - 1` SO IT SEES WHAT EVERYBODY ELSE DID. A
	 * filter at the default priority would decide "there is no Sitemap line"
	 * before core's own had run.
	 *
	 * @param string $output The robots.txt body so far.
	 * @param bool   $public Whether WordPress considers the site public.
	 * @return string
	 */
	public static function announce_sitemap( $output, $public ) {
		$output = (string) $output;

		if ( ! $public || ! Goaiez_Options::announce_sitemap() ) {
			return $output;
		}

		if ( self::names_a_sitemap( $output ) ) {
			return $output;
		}

		$index = self::core_sitemap_index();

		if ( '' === $index ) {
			return $output;
		}

		return $output . "\nSitemap: " . esc_url( $index ) . "\n";
	}

	/**
	 * Whether a robots.txt body already carries a live `Sitemap:` directive.
	 *
	 * ⛔ A COMMENTED-OUT LINE IS NOT A DIRECTIVE, AND GETTING THAT WRONG HAS
	 * ALREADY COST THIS PROJECT A BUG. Decision 5698: the platform's own parser
	 * used `strtok( $line, '#' )`, which skips leading delimiters, so
	 * `# Sitemap: …/decoy.xml` — exactly how somebody disables one — came back
	 * as live. `explode()` with a limit is the fix, and it is the same fix here
	 * because the failure would be the mirror image: seeing a directive that is
	 * not there and declining to add the one that should be.
	 *
	 * @param string $output The robots.txt body.
	 * @return bool
	 */
	public static function names_a_sitemap( $output ) {
		foreach ( preg_split( '/\R/', (string) $output ) as $line ) {
			$parts      = explode( '#', $line, 2 );
			$directive  = trim( $parts[0] );

			if ( 0 === stripos( $directive, 'sitemap:' ) && '' !== trim( substr( $directive, 8 ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Core's sitemap index URL, or '' when core sitemaps are switched off.
	 *
	 * @return string
	 */
	public static function core_sitemap_index() {
		if ( ! function_exists( 'wp_sitemaps_get_server' ) ) {
			return '';
		}

		$server = wp_sitemaps_get_server();

		if ( ! $server || ! $server->sitemaps_enabled() ) {
			return '';
		}

		return (string) $server->index->get_index_url();
	}

	/**
	 * What only this plugin can see about the site's robots and sitemap
	 * arrangement, for the status route.
	 *
	 * ⚠️ IT REPORTS FACTS AND DOES NOT READ robots.txt ITSELF. The platform
	 * already fetches the tenant's robots.txt through its own gateway, where the
	 * robots policy, the attempt ledger and the kill switch live (5695). Running
	 * the filter chain in here to produce a preview would run every other
	 * plugin's `robots_txt` filter out of context, for a string nobody reads.
	 *
	 * @return array
	 */
	public static function report() {
		return array(
			'site_is_public'          => (bool) get_option( 'blog_public' ),
			'core_sitemaps_enabled'   => '' !== self::core_sitemap_index(),
			'core_sitemap_index'      => self::core_sitemap_index(),
			'physical_robots_txt'     => file_exists( ABSPATH . 'robots.txt' ),
			'announcement_enabled'    => Goaiez_Options::announce_sitemap(),
		);
	}
}
