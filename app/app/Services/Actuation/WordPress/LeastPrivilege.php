<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

use App\Enums\WordPressConnectionRefusal;
use App\Services\Actuation\ChangeSet;

/**
 * §19.7's least-privilege gate, as a mechanism rather than a claim — decision
 * 5582.
 *
 * ⛔ **AN APPLICATION PASSWORD IS NOT A SCOPE, AND THE PLAN READ AS THOUGH IT
 * WERE.** WordPress's own launch guide is explicit: application passwords carry
 * the full capabilities of the user they belong to, and scoping them was
 * discussed and not implemented
 * (`make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/`,
 * published 2020-11-05, fetched 2026-08-19). So one minted on an Administrator
 * can install plugins, edit users and execute code — **the exact three things
 * §19.7 says the credential cannot do**. `19` §3.3's phrasing, *"Application
 * Passwords / scoped REST credentials"*, reads as though those were one thing.
 *
 * **The property is therefore about the WordPress *user*, and the only honest
 * way to assert it is to read that user's capabilities off the live site.**
 *
 * ## Two arms, because one of them has a hole the other closes
 *
 * **Arm 1 — the self-reported map.** `GET /wp/v2/users/me?context=edit` returns
 * `capabilities`, documented as *"All capabilities assigned to the user"* and
 * populated in core from `$user->allcaps`, which is a map of capability name to
 * boolean. {@see self::excessive()} is that arm. It catches every single-site
 * Administrator and every custom role somebody over-granted.
 *
 * ⛔ **AND IT HAS A REAL HOLE: A MULTISITE SUPER ADMIN IS NOT IN `allcaps`.**
 * Super-admin status lives in a network option, not in a role, and
 * `WP_User::has_cap()` short-circuits for it — so a Super Admin whose site role
 * is Editor reports an Editor's capability map while being able to install
 * plugins across the network. Arm 1 alone would wave that credential through
 * while saying, in a docblock, that it could not install plugins: 314–316's
 * failure, inside the gate written to prevent it.
 *
 * **Arm 2 — the behavioural probe.** `GET /wp/v2/plugins` is core's own plugins
 * endpoint and its permission check is one line —
 * `if ( ! current_user_can( 'activate_plugins' ) ) return new WP_Error(
 * 'rest_cannot_view_plugins', … )` (verified against
 * `WP_REST_Plugins_Controller::get_items_permissions_check`, 2026-08-19). An
 * Editor is refused; an Administrator and a Super Admin are not. It is
 * **read-only** — listing plugins, never touching one — and it asks the site the
 * question rather than asking the credential about itself.
 *
 * ⚠️ **ARM 2 REFUSES ONLY ON POSITIVE EVIDENCE**, and that asymmetry is
 * deliberate. A 404 means the endpoint is absent — core added it in 5.5 and a
 * security plugin removing it is ordinary — and absence is not evidence of
 * power. Treating an unreachable probe as a refusal would make the gate fail on
 * hardened sites, which is where it is least needed and most likely to be
 * switched off.
 *
 * ## Where the floor comes from, and why it is nearly the ceiling
 *
 * ⚠️ **THE CREDENTIAL MUST ALSO BE ABLE TO DO THE WORK.** Editing a page written
 * by somebody else needs `edit_others_pages` and `edit_published_pages`; an
 * Author has neither. So the floor is an Editor and the ceiling is one rung
 * above it, and there is very little room in between — which is worth knowing
 * before somebody widens either list. Every capability name below is taken from
 * the capability-versus-role table at
 * `wordpress.org/documentation/article/roles-and-capabilities/` (last updated
 * 2024-09-20, fetched 2026-08-19), not from memory.
 *
 * ⛔ **`unfiltered_html` IS DELIBERATELY NOT FORBIDDEN, AND IT LOOKS LIKE IT
 * SHOULD BE.** It lets a user post JavaScript into a page, which reads as "can
 * execute code". But **an Editor has it on every single-site install by
 * default**, so forbidding it would refuse the exact role this gate asks the
 * owner to create — a gate refusing its own recommended answer gets widened
 * until it catches nothing (511). The residual risk is ours rather than the
 * credential's: what we write is a map of named fields, never free-form markup
 * ({@see ChangeSet}), so the capability is unused in
 * both directions. ⚠️ **Its absence has a consequence in the other direction and
 * {@see WordPressRestClient} is where that is handled**: without it WordPress
 * silently strips disallowed markup and answers `200`.
 */
final class LeastPrivilege
{
    /**
     * The capabilities §19.7's three prohibitions actually name.
     *
     * ⚠️ **GROUPED BY PROHIBITION RATHER THAN ALPHABETICALLY**, because the next
     * person to edit this list needs to answer "which of the three is this" and
     * a sorted list hides the question.
     *
     * ⛔ **`manage_options` IS UNDER "EXECUTE CODE" AND THAT IS NOT A STRETCH.**
     * Arbitrary option writes are the standard route from Settings to running
     * code on a WordPress install, and it is the capability that makes a user an
     * administrator in practice.
     *
     * ⚠️ **`list_users`, `import` and `export` ARE DELIBERATELY ABSENT.** They
     * are real over-grants and none of them is one of the three; a gate that
     * refuses on everything an Editor happens to lack stops being §19.7's gate
     * and starts being a role check wearing its clothes.
     *
     * @var list<string>
     */
    public const array FORBIDDEN = [
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
    ];

    /**
     * What the credential has to be able to do, or connecting it buys nothing.
     *
     * ⚠️ **`edit_others_*` IS THE ONE THAT MATTERS AND IT IS EASY TO LEAVE
     * OUT.** Every page this platform edits was written by somebody else — the
     * owner, their web person, a theme's importer — so a credential that can
     * only edit its own posts passes a naive check and then fails at the first
     * write, on a change set that has already been opened and cannot be applied.
     *
     * @var list<string>
     */
    public const array REQUIRED = [
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'edit_pages',
        'edit_others_pages',
        'edit_published_pages',
    ];

    /**
     * The forbidden capabilities this credential actually holds.
     *
     * ⚠️ **A `false` VALUE IS NOT A GRANT.** `allcaps` is a map, and a role that
     * has had a capability explicitly removed carries the key with `false`.
     * Reading it with `array_key_exists()` would refuse a credential for a
     * capability WordPress has already denied it.
     *
     * @param  array<string, mixed>  $capabilities
     * @return list<string>
     */
    public static function excessive(array $capabilities): array
    {
        return array_values(array_filter(
            self::FORBIDDEN,
            static fn (string $cap): bool => (bool) ($capabilities[$cap] ?? false),
        ));
    }

    /**
     * The capabilities the work needs and this credential does not have.
     *
     * @param  array<string, mixed>  $capabilities
     * @return list<string>
     */
    public static function missing(array $capabilities): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $cap): bool => ! (bool) ($capabilities[$cap] ?? false),
        ));
    }

    /**
     * The gate's answer, or null if the credential is exactly right.
     *
     * ⚠️ **TOO POWERFUL IS CHECKED FIRST AND THE ORDER IS LOAD-BEARING.** An
     * Administrator satisfies every required capability, so a gate that answered
     * `TooWeak` first would still refuse them — but an owner told "give this user
     * the Editor role" after pasting an administrator's password would be being
     * pointed at a change that makes nothing better.
     *
     * @param  array<string, mixed>  $capabilities  `capabilities` from
     *                                              `/wp/v2/users/me?context=edit`.
     * @param  bool|null  $canManagePlugins  Arm 2's answer, or null when the
     *                                       probe could not be run at all.
     */
    public static function refusalFor(array $capabilities, ?bool $canManagePlugins = null): ?WordPressConnectionRefusal
    {
        if ($canManagePlugins === true || self::excessive($capabilities) !== []) {
            return WordPressConnectionRefusal::TooPowerful;
        }

        if (self::missing($capabilities) !== []) {
            return WordPressConnectionRefusal::TooWeak;
        }

        return null;
    }
}
