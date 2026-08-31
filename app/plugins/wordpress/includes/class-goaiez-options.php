<?php

/**
 * Every stored setting this plugin has, in one place.
 */
defined('ABSPATH') || exit;

/**
 * The plugin's own settings, and the only code that reads or writes them.
 *
 * ⚠️ ONE DOOR, FOR THE SAME REASON THE PLATFORM USES CHOKEPOINTS: a secret read
 * in six places is a secret logged in one of them. Nothing outside this class
 * calls `get_option()` for a `goaiez_` key, and the platform's test suite lints
 * for it.
 *
 * ⛔ EVERY OPTION IS REGISTERED WITH AUTOLOAD OFF. An autoloaded option is
 * fetched on every single page view of the site, including by other plugins
 * dumping `wp_load_alloptions()` into a debug bar, and one of these is a shared
 * secret.
 *
 * ⚠️ AND THE SECRET IS STILL ONLY AS PRIVATE AS THE SITE'S DATABASE. Anybody
 * with `manage_options`, database access, or a backup file has it — which is a
 * fact about WordPress rather than a shortcoming here, and is why the gate
 * beneath it is the acting user's capabilities rather than the secret's
 * secrecy: a stolen secret still cannot install a plugin, edit a user or run
 * code, because the user it acts as cannot.
 */
class Goaiez_Options
{
    const SECRET = 'goaiez_secret';

    const ACTOR = 'goaiez_actor_user_id';

    const WRITE_ACCESS = 'goaiez_write_access';

    const INDEXNOW_KEY = 'goaiez_indexnow_key';

    const ANNOUNCE = 'goaiez_announce_sitemap';

    const SPEED = 'goaiez_speed_fixes';

    const INVENTORY = 'goaiez_site_inventory';

    const PAIRED_AT = 'goaiez_paired_at';

    /**
     * Every option key this plugin owns, for uninstall and for the tests.
     *
     * @return string[]
     */
    public static function all_keys()
    {
        return [
            self::SECRET,
            self::ACTOR,
            self::WRITE_ACCESS,
            self::INDEXNOW_KEY,
            self::ANNOUNCE,
            self::SPEED,
            self::INVENTORY,
            self::PAIRED_AT,
        ];
    }

    /**
     * This site's shared secret, or '' when it has never been paired.
     *
     * @return string
     */
    public static function secret()
    {
        $value = get_option(self::SECRET, '');

        return is_string($value) ? $value : '';
    }

    /**
     * Mint a new secret, replacing any existing one.
     *
     * ⛔ GENERATED HERE, ON THE SITE, AND NEVER SENT ANYWHERE BY THIS PLUGIN.
     * The owner copies it from their own admin screen into GO AI EZ, which is
     * the same shape as WordPress's own Application Passwords flow and means
     * this plugin makes no outbound request in its whole lifetime. Rotating is
     * pressing the button again: the old secret stops working at that instant.
     *
     * @return string The new secret.
     */
    public static function mint_secret()
    {
        $secret = bin2hex(random_bytes(32));

        update_option(self::SECRET, $secret, false);
        update_option(self::PAIRED_AT, time(), false);
        update_option(self::WRITE_ACCESS, 'on', false);

        return $secret;
    }

    /**
     * Forget the secret. Nothing signed will verify again.
     *
     * @return void
     */
    public static function forget_secret()
    {
        delete_option(self::SECRET);
        delete_option(self::PAIRED_AT);
    }

    /**
     * The WordPress user this plugin acts as, or 0.
     *
     * @return int
     */
    public static function actor()
    {
        return (int) get_option(self::ACTOR, 0);
    }

    /**
     * Choose the acting user.
     *
     * @param  int  $user_id  WordPress user id, or 0 to clear.
     * @return void
     */
    public static function set_actor($user_id)
    {
        update_option(self::ACTOR, (int) $user_id, false);
    }

    /**
     * `19` §3.3's kill switch: whether write access is severed.
     *
     * ⛔ IT FAILS CLOSED FOR AN UNPAIRED SITE AND OPEN FOR A PAIRED ONE, AND
     * BOTH DIRECTIONS ARE DELIBERATE. A site that has never been paired has no
     * secret, so nothing verifies and the answer is moot. A site that has just
     * been paired must work, or the owner's next act is a support ticket. The
     * switch exists to be *thrown*, and the state that matters is the one it
     * moves to.
     *
     * @return bool
     */
    public static function write_access()
    {
        return get_option(self::WRITE_ACCESS, 'on') !== 'off';
    }

    /**
     * Throw the kill switch, or put it back.
     *
     * @param  bool  $on  Whether writes are allowed.
     * @return void
     */
    public static function set_write_access($on)
    {
        update_option(self::WRITE_ACCESS, $on ? 'on' : 'off', false);
    }

    /**
     * The IndexNow key this site serves, or '' for none.
     *
     * @return string
     */
    public static function indexnow_key()
    {
        $value = get_option(self::INDEXNOW_KEY, '');

        return is_string($value) ? $value : '';
    }

    /**
     * Set or clear the IndexNow key.
     *
     * @param  string  $key  Lower-case hex, 8 to 128 characters, or '' to clear.
     * @return void
     */
    public static function set_indexnow_key($key)
    {
        if ($key === '') {
            delete_option(self::INDEXNOW_KEY);

            return;
        }

        update_option(self::INDEXNOW_KEY, $key, false);
    }

    /**
     * Whether this plugin should add the sitemap line to robots.txt.
     *
     * ⚠️ DEFAULT OFF, WHICH IS THE OPPOSITE OF WHAT THE PLAN ASSUMED. Decision
     * 5683 found WordPress core has announced its own sitemap index in
     * robots.txt since 5.5, so the common case needs nothing and switching this
     * on by default would add a second, duplicate line to most sites on earth.
     *
     * @return bool
     */
    public static function announce_sitemap()
    {
        return get_option(self::ANNOUNCE, 'off') === 'on';
    }

    /**
     * Turn the sitemap announcement on or off.
     *
     * @param  bool  $on  Whether to announce.
     * @return void
     */
    public static function set_announce_sitemap($on)
    {
        update_option(self::ANNOUNCE, $on ? 'on' : 'off', false);
    }

    /**
     * The speed fixes currently live on this site, keyed by fix name.
     *
     * @return array
     */
    public static function speed_fixes()
    {
        $value = get_option(self::SPEED, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Replace the live speed fixes.
     *
     * @param  array  $fixes  Fix name => payload array.
     * @return void
     */
    public static function set_speed_fixes(array $fixes)
    {
        update_option(self::SPEED, $fixes, false);
    }

    /**
     * What the last front-end page render saw itself loading.
     *
     * @return array
     */
    public static function inventory()
    {
        $value = get_option(self::INVENTORY, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Record what a front-end page render saw itself loading.
     *
     * @param  array  $inventory  The inventory.
     * @return void
     */
    public static function set_inventory(array $inventory)
    {
        update_option(self::INVENTORY, $inventory, false);
    }

    /**
     * When the site was paired, or 0.
     *
     * @return int
     */
    public static function paired_at()
    {
        return (int) get_option(self::PAIRED_AT, 0);
    }

    /**
     * Deactivation.
     *
     * ⛔ IT DELETES NOTHING, AND THAT IS THE DECISION. Deactivating already
     * severs every route, filter and hook this plugin has — the kill switch by
     * the bluntest means available — so destroying the secret as well would turn
     * a mis-click into a re-pairing. What it does do is drop the live speed
     * fixes, because those are filters that stop running anyway and a stored
     * list saying otherwise would be a lie the admin screen renders on
     * reactivation.
     *
     * @return void
     */
    public static function on_deactivate()
    {
        update_option(self::SPEED, [], false);
    }
}
