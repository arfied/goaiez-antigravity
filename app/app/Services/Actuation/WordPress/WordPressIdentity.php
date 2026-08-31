<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

/**
 * Who a WordPress Application Password actually belongs to, read off the live
 * site.
 *
 * ⚠️ **THE THREE FIELDS COME FROM `GET /wp/v2/users/me?context=edit` AND ALL
 * THREE ARE `edit`-CONTEXT-ONLY.** WordPress's user reference marks `roles`,
 * `capabilities` and `extra_capabilities` as available in `edit` context only,
 * and core populates them from `$user->roles`, `$user->allcaps` and
 * `$user->caps` respectively (verified against
 * `WP_REST_Users_Controller::prepare_item_for_response`, 2026-08-19). A probe
 * that forgot `context=edit` would get a `200` with **no capabilities at all**,
 * and {@see LeastPrivilege} would read that as an empty map — refusing every
 * credential as `TooWeak`, which is at least the safe direction, but for a
 * reason nobody could diagnose.
 *
 * ⚠️ **`capabilities` IS A MAP TO BOOLEANS, NOT A LIST.** A role that has had a
 * capability explicitly removed carries the key with `false`.
 */
final readonly class WordPressIdentity
{
    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        public int $userId,
        public array $roles,
        public array $capabilities,
    ) {}
}
