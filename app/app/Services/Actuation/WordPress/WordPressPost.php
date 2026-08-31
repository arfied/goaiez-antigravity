<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

/**
 * One post or page on a tenant's site, resolved from the URL a change set names.
 *
 * ⚠️ **`$type` IS THE REST BASE (`posts` / `pages`), NOT THE POST TYPE.** Core's
 * routes are `/wp/v2/posts` and `/wp/v2/pages` while the post types are `post`
 * and `page`, and holding the singular here would mean pluralising it at every
 * call site — which is exactly how one of them ends up wrong.
 *
 * ⛔ **`$link` IS CARRIED SO IT CAN BE CHECKED, NOT SO IT CAN BE DISPLAYED.**
 * Core REST has no permalink lookup, so a URL is resolved by taking its last
 * path segment as a slug and asking `?slug=…`. A slug is unique per post type
 * and **not** unique per URL: a child page and a top-level page can share one,
 * and so can two pages under different parents. Writing to whichever came back
 * first would edit a different page on a stranger's website and record the
 * change against the URL we meant. {@see WordPressRestClient} compares this
 * against the requested URL and refuses on a mismatch.
 *
 * `$fields` holds the `raw` values in `edit` context — the strings a rollback
 * has to be able to put back, not the rendered HTML a visitor sees.
 */
final readonly class WordPressPost
{
    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(
        public string $type,
        public int $id,
        public string $link,
        public array $fields,
    ) {}
}
