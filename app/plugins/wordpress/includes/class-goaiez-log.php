<?php

/**
 * The change log that lives on the customer's own site.
 */
defined('ABSPATH') || exit;

/**
 * Every change GO AI EZ has made to this website, written down here, on this
 * website.
 *
 * ⛔ THIS IS `29` §2 RULE 32 FROM THE OTHER SIDE. The platform keeps a
 * `site_changes` row for every write and offers the owner an Undo on a screen we
 * host. That is worth nothing on the day the owner wants us gone, or the day our
 * screen is down, or the day they simply do not want to log in to somebody
 * else's product to fix their own website. `19` §3.3: *"Every change logged
 * locally in WP and platform-side, with one-click revert inside WP."* The word
 * that matters is **and**.
 *
 * ⛔ SO THE REVERT PATH IN HERE MUST NOT NEED US. It reads this table, writes
 * back through WordPress's own APIs, and makes no request to anything. If the
 * platform never speaks to this site again, every change it ever made is still
 * listed, still explained in plain words, and still one click from being undone.
 *
 * ⚠️ THE TABLE SURVIVES UNINSTALL AND `uninstall.php` SAYS WHY. Deleting it
 * would destroy the owner's only record of what was done to their site and their
 * only route back — and the changes themselves are in their pages, so removing
 * the log removes the map and not the territory. There is a button on the admin
 * screen for an owner who wants it gone anyway.
 */
class Goaiez_Log
{
    /**
     * The table, with the site's own prefix.
     *
     * @return string
     */
    public static function table()
    {
        global $wpdb;

        return $wpdb->prefix.'goaiez_changes';
    }

    /**
     * Create the table. Runs on activation.
     *
     * ⚠️ `dbDelta()` IS FUSSY ABOUT WHITESPACE AND KEY SYNTAX and silently does
     * nothing when it is unhappy, so this is written in the shape core's own
     * schema uses rather than the shape that reads best.
     *
     * @return void
     */
    public static function install()
    {
        global $wpdb;

        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        $table = self::table();
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			change_id varchar(64) NOT NULL,
			kind varchar(20) NOT NULL,
			object_type varchar(20) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			url text NOT NULL,
			summary text NOT NULL,
			before_json longtext NOT NULL,
			after_json longtext NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			applied_at datetime NOT NULL,
			reverted_at datetime DEFAULT NULL,
			reverted_by varchar(20) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY change_id (change_id),
			KEY applied_at (applied_at)
		) {$collate};";

        dbDelta($sql);
    }

    /**
     * The row for one platform change id, or null.
     *
     * @param  string  $change_id  The platform's change id.
     * @return array|null
     */
    public static function find($change_id)
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not input.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE change_id = %s", (string) $change_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * The most recent changes, newest first.
     *
     * @param  int  $limit  How many.
     * @return array[]
     */
    public static function recent($limit = 100)
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not input.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", (int) $limit), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Record a change.
     *
     * ⛔ THE PRIOR STATE IS NOT OPTIONAL AND A ROW CANNOT BE WRITTEN WITHOUT
     * ONE. Rule 32's first half is the snapshot, and the platform enforces it at
     * `SiteChanges::open()`. A second enforcement here is not belt-and-braces:
     * this is the table the owner's undo reads, and a row with no `before` is a
     * change with an Undo button that cannot do anything.
     *
     * ⚠️ A CREATION'S PRIOR STATE IS `{"_page_state":"absent"}` — decision
     * 5770's recorded observation rather than an empty map, and the same
     * sentinel the platform writes, so the two logs describe the same fact in
     * the same words.
     *
     * @param  array  $change  change_id, kind, object_type, object_id, url,
     *                         summary, before, after, actor_user_id.
     * @return bool
     */
    public static function record(array $change)
    {
        global $wpdb;

        if (empty($change['change_id']) || ! isset($change['before']) || ! is_array($change['before']) || $change['before'] === []) {
            return false;
        }

        $inserted = $wpdb->insert(
            self::table(),
            [
                'change_id' => (string) $change['change_id'],
                'kind' => (string) $change['kind'],
                'object_type' => isset($change['object_type']) ? (string) $change['object_type'] : 'post',
                'object_id' => isset($change['object_id']) ? (int) $change['object_id'] : 0,
                'url' => isset($change['url']) ? (string) $change['url'] : '',
                'summary' => isset($change['summary']) ? (string) $change['summary'] : '',
                'before_json' => wp_json_encode($change['before']),
                'after_json' => wp_json_encode(isset($change['after']) ? $change['after'] : []),
                'actor_user_id' => isset($change['actor_user_id']) ? (int) $change['actor_user_id'] : 0,
                'applied_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        return $inserted !== false;
    }

    /**
     * Mark a change as reverted.
     *
     * @param  string  $change_id  The platform's change id.
     * @param  string  $reverted_by  'owner' or 'platform'.
     * @return void
     */
    public static function mark_reverted($change_id, $reverted_by)
    {
        global $wpdb;

        $wpdb->update(
            self::table(),
            [
                'reverted_at' => gmdate('Y-m-d H:i:s'),
                'reverted_by' => $reverted_by === 'owner' ? 'owner' : 'platform',
            ],
            ['change_id' => (string) $change_id],
            ['%s', '%s'],
            ['%s']
        );
    }

    /**
     * The stored `before` map for a row.
     *
     * @param  array  $row  A row from this table.
     * @return array
     */
    public static function before(array $row)
    {
        $decoded = json_decode(isset($row['before_json']) ? $row['before_json'] : '', true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Throw the whole log away, at the owner's request.
     *
     * @return void
     */
    public static function drop()
    {
        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name, not input; this is the plugin's own table.
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
