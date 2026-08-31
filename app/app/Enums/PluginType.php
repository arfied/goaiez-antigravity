<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an embeddable plugin is (DATA-MODEL §5.11 `plugins.type`).
 *
 * ONE CASE, AND THE MIGRATION'S OWN COMMENT LISTS FOUR. `plugins.type` was
 * created with `review_widget|badge|feedback_form|…` written above it as a plain
 * string column, and that comment is a wish list rather than a vocabulary:
 * nothing validated it, nothing wrote it, and three of the four have no consumer
 * in this codebase at all. A case here is a promise that something renders it,
 * so only the one slice G actually serves exists.
 *
 * A PHP enum over a string column, never a database enum — `CLAUDE.md`'s
 * standing rule. The reasons that bite here are the general ones: a database
 * enum is a second source of truth that drifts from the PHP one, and Postgres
 * enum values can never be dropped or reordered once added, which is exactly the
 * wrong property for a set the migration already expects to grow.
 */
enum PluginType: string
{
    /**
     * The review feed FPR-05 serves and FPR-06's script would render.
     *
     * ⚠️ THE SCRIPT DOES NOT EXIST. `BUILD-PLAN` §2.6.4 conflict 3 defers the
     * embeddable bundle — it is the same problem as the pixel (versioning, a
     * CDN, a size budget) and decision 89 pushed that out. What ships is the
     * server half: the key, the origin allowlist, the config and the JSON. So a
     * `review_widget` row today means "this location has a feed something could
     * consume", never "this location has a widget on their site".
     */
    case ReviewWidget = 'review_widget';
}
