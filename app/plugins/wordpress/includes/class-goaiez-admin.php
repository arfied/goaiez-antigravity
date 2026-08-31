<?php

/**
 * The screen the site owner uses — including the kill switch and the undo.
 */
defined('ABSPATH') || exit;

/**
 * Everything the owner of this website can do about GO AI EZ, without GO AI EZ.
 *
 * ⛔ THIS SCREEN IS THE POINT OF THE PLUGIN AS MUCH AS THE REST ROUTES ARE.
 * `19` §3.3 asks for *"one-click revert inside WP"* and a *"kill switch in the
 * plugin that severs write access instantly"*, and both of those are worthless
 * if they route back through us. Nothing on this page makes a request to
 * anything: the undo reads this site's own log and writes through WordPress's
 * own API, and the kill switch is a row in this site's own options table.
 *
 * ⛔ `manage_options` ON THE PAGE AND ON EVERY ACTION, PLUS A NONCE ON EVERY
 * FORM. The capability is checked again inside the handler and not only when
 * the menu is built, because a menu is a link and a link is a URL somebody can
 * be sent. WordPress's own guidance is *"make sure to run your code only when
 * the current user has the necessary capabilities"*
 * (`developer.wordpress.org/plugins/security/checking-user-capabilities/`,
 * fetched 2026-08-20).
 *
 * ⚠️ AND THE OWNER'S UNDO IS PERFORMED AS THE OWNER, NOT AS THE ACTING USER.
 * They are the one who pressed it, it is their website, and the kill switch does
 * not apply to them — refusing an owner's undo because they had already switched
 * our write access off would be this plugin holding a change hostage to the
 * control built to protect them from it.
 */
class Goaiez_Admin
{
    const SLUG = 'goaiez';

    /**
     * Put the screen in the menu.
     *
     * @return void
     */
    public static function register_page()
    {
        add_options_page(
            __('GO AI EZ', 'goaiez'),
            __('GO AI EZ', 'goaiez'),
            'manage_options',
            self::SLUG,
            [__CLASS__, 'render']
        );
    }

    /**
     * Handle a button press.
     *
     * @return void
     */
    public static function handle_post()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change this.', 'goaiez'), '', ['response' => 403]);
        }

        check_admin_referer('goaiez_action');

        $action = isset($_POST['goaiez_do']) ? sanitize_key(wp_unslash($_POST['goaiez_do'])) : '';
        $notice = '';

        switch ($action) {
            case 'pair':
                $secret = Goaiez_Options::mint_secret();
                set_transient('goaiez_reveal_'.get_current_user_id(), $secret, 300);
                $notice = 'paired';
                break;

            case 'unpair':
                Goaiez_Options::forget_secret();
                $notice = 'unpaired';
                break;

            case 'kill':
                Goaiez_Options::set_write_access(false);
                $notice = 'severed';
                break;

            case 'restore':
                Goaiez_Options::set_write_access(true);
                $notice = 'restored';
                break;

            case 'actor':
                $actor = isset($_POST['goaiez_actor']) ? (int) wp_unslash($_POST['goaiez_actor']) : 0;

                if ($actor !== 0 && Goaiez_Capabilities::refusal_for_user($actor) !== null) {
                    $notice = 'actor_refused';
                    break;
                }

                Goaiez_Options::set_actor($actor);
                $notice = 'actor_set';
                break;

            case 'revert':
                $change_id = isset($_POST['goaiez_change']) ? sanitize_text_field(wp_unslash($_POST['goaiez_change'])) : '';
                $result = Goaiez_Writer::revert($change_id, 'owner');
                $notice = is_wp_error($result) ? 'undo_failed' : 'undone';
                break;

            case 'drop_log':
                Goaiez_Log::drop();
                $notice = 'log_dropped';
                break;
        }

        wp_safe_redirect(add_query_arg('goaiez_notice', $notice, admin_url('options-general.php?page='.self::SLUG)));
        exit;
    }

    /**
     * Draw the screen.
     *
     * @return void
     */
    public static function render()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $reveal = get_transient('goaiez_reveal_'.get_current_user_id());

        if ($reveal !== false) {
            delete_transient('goaiez_reveal_'.get_current_user_id());
        }

        $paired = Goaiez_Options::secret() !== '';
        $actor = Goaiez_Options::actor();
        $refusal = Goaiez_Capabilities::refusal_for_user($actor);

        echo '<div class="wrap">';
        echo '<h1>'.esc_html__('GO AI EZ', 'goaiez').'</h1>';

        self::notice();

        // ⚠️ SHOWN ONCE AND NEVER AGAIN, LIKE WORDPRESS'S OWN APPLICATION
        // PASSWORDS SCREEN. It is still in this site's database — that is
        // unavoidable — but a page that reprints it every time somebody opens
        // Settings is a page that leaks it over somebody's shoulder.
        if ($reveal !== false) {
            echo '<div class="notice notice-success"><p><strong>'.esc_html__('Copy this into GO AI EZ now. It is not shown again.', 'goaiez').'</strong></p>';
            echo '<p><code style="user-select:all">'.esc_html($reveal).'</code></p></div>';
        }

        echo '<h2>'.esc_html__('Connection', 'goaiez').'</h2>';
        echo '<p>'.($paired
            ? esc_html__('This site is connected to GO AI EZ.', 'goaiez')
            : esc_html__('This site is not connected to GO AI EZ yet.', 'goaiez')).'</p>';

        self::form($paired ? 'pair' : 'pair', $paired ? __('Create a new connection code', 'goaiez') : __('Create a connection code', 'goaiez'));

        if ($paired) {
            self::form('unpair', __('Disconnect this site', 'goaiez'));
        }

        echo '<h2>'.esc_html__('Write access', 'goaiez').'</h2>';

        if (Goaiez_Options::write_access()) {
            echo '<p>'.esc_html__('GO AI EZ can make the changes you have asked for. Switching this off stops every change immediately; nothing already on your site is undone.', 'goaiez').'</p>';
            self::form('kill', __('Stop GO AI EZ changing this site', 'goaiez'));
        } else {
            echo '<p>'.esc_html__('GO AI EZ cannot change anything on this site.', 'goaiez').'</p>';
            self::form('restore', __('Let GO AI EZ change this site again', 'goaiez'));
        }

        echo '<h2>'.esc_html__('Who GO AI EZ acts as', 'goaiez').'</h2>';
        echo '<p>'.esc_html__('Pick a user with the Editor role. GO AI EZ can never do more on this site than the user you pick, and it refuses any user who can install plugins, edit users or edit files.', 'goaiez').'</p>';

        if ($actor !== 0 && $refusal !== null) {
            echo '<div class="notice notice-error inline"><p>'.esc_html(
                $refusal === 'too_powerful'
                    ? __('The user chosen has too much access, so nothing will be changed until another is picked.', 'goaiez')
                    : __('The user chosen cannot edit pages, so nothing will be changed until another is picked.', 'goaiez')
            ).'</p></div>';
        }

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('goaiez_action');
        echo '<input type="hidden" name="action" value="goaiez_action" />';
        echo '<input type="hidden" name="goaiez_do" value="actor" />';
        echo '<select name="goaiez_actor">';
        echo '<option value="0">'.esc_html__('Nobody — GO AI EZ changes nothing', 'goaiez').'</option>';

        foreach (Goaiez_Capabilities::eligible_users() as $user) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $user->ID,
                selected($actor, $user->ID, false),
                esc_html($user->display_name.' ('.$user->user_login.')')
            );
        }

        echo '</select> ';
        submit_button(__('Save', 'goaiez'), 'secondary', 'submit', false);
        echo '</form>';

        self::render_log();

        echo '</div>';
    }

    /**
     * The change history, with an undo on every row.
     *
     * @return void
     */
    private static function render_log()
    {
        echo '<h2>'.esc_html__('What GO AI EZ has changed on this site', 'goaiez').'</h2>';

        $rows = Goaiez_Log::recent(100);

        if ($rows === []) {
            echo '<p>'.esc_html__('Nothing has been changed on this site.', 'goaiez').'</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>'.esc_html__('When', 'goaiez').'</th>';
        echo '<th>'.esc_html__('Page', 'goaiez').'</th>';
        echo '<th>'.esc_html__('What changed', 'goaiez').'</th>';
        echo '<th>'.esc_html__('Undo', 'goaiez').'</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>'.esc_html($row['applied_at']).'</td>';
            echo '<td><a href="'.esc_url($row['url']).'">'.esc_html($row['url']).'</a></td>';
            echo '<td>'.esc_html($row['summary']).'</td>';
            echo '<td>';

            if (! empty($row['reverted_at'])) {
                echo esc_html(
                    $row['reverted_by'] === 'owner'
                        ? __('You undid this', 'goaiez')
                        : __('Undone', 'goaiez')
                );
            } else {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                wp_nonce_field('goaiez_action');
                echo '<input type="hidden" name="action" value="goaiez_action" />';
                echo '<input type="hidden" name="goaiez_do" value="revert" />';
                echo '<input type="hidden" name="goaiez_change" value="'.esc_attr($row['change_id']).'" />';
                submit_button(__('Undo this', 'goaiez'), 'secondary small', 'submit', false);
                echo '</form>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';

        // ⚠️ THE HISTORY OUTLIVES THE PLUGIN UNLESS THE OWNER SAYS OTHERWISE,
        // AND THIS IS THE SAYING OTHERWISE. Deleting the plugin leaves this
        // table in place on purpose: it is the record of what was done to their
        // website and the only route back, and the changes themselves live in
        // their pages rather than in here.
        echo '<p>'.esc_html__('This history stays on your site even if you delete the plugin, so you can always see what was changed and undo it.', 'goaiez').'</p>';
        self::form('drop_log', __('Delete this history', 'goaiez'));
    }

    /**
     * A one-button form.
     *
     * @param  string  $do  The action name.
     * @param  string  $label  The button label.
     * @return void
     */
    private static function form($do, $label)
    {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('goaiez_action');
        echo '<input type="hidden" name="action" value="goaiez_action" />';
        echo '<input type="hidden" name="goaiez_do" value="'.esc_attr($do).'" />';
        submit_button($label, 'secondary', 'submit', false);
        echo '</form>';
    }

    /**
     * Whatever the last press did.
     *
     * @return void
     */
    private static function notice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a redirect marker to choose a sentence; it changes nothing.
        $notice = isset($_GET['goaiez_notice']) ? sanitize_key(wp_unslash($_GET['goaiez_notice'])) : '';

        $sentences = [
            'paired' => __('Connection code created.', 'goaiez'),
            'unpaired' => __('This site is disconnected. GO AI EZ can no longer change anything here.', 'goaiez'),
            'severed' => __('GO AI EZ can no longer change this site.', 'goaiez'),
            'restored' => __('GO AI EZ can change this site again.', 'goaiez'),
            'actor_set' => __('Saved.', 'goaiez'),
            'actor_refused' => __('That user was not saved: GO AI EZ will not act as someone who can install plugins, edit users or edit files.', 'goaiez'),
            'undone' => __('That change has been undone.', 'goaiez'),
            'undo_failed' => __('That change could not be undone. Nothing on your site was altered.', 'goaiez'),
            'log_dropped' => __('The history has been deleted.', 'goaiez'),
        ];

        if (isset($sentences[$notice])) {
            $class = in_array($notice, ['actor_refused', 'undo_failed'], true) ? 'notice-error' : 'notice-success';

            echo '<div class="notice '.esc_attr($class).'"><p>'.esc_html($sentences[$notice]).'</p></div>';
        }
    }
}
