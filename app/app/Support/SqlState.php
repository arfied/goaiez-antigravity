<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * The Postgres SQLSTATE code behind a query exception, or null.
 *
 * `$e->getCode()` can return an int rather than a string depending on the
 * driver and the failure, and `$e->errorInfo[0] ?? $e->getCode()` inherits
 * that — so a bare `=== '23505'`-style comparison against the unguarded value
 * silently returns false when the code happens to be an int, and a real
 * SQLSTATE match is missed with no error at all. `Tenancy::applyToDatabase()`
 * had the `is_string()` guard; `FeedbackPages::isUniqueViolation()` copied the
 * two-line pattern without it, which is what this class exists to stop from
 * happening a third time.
 */
final class SqlState
{
    public static function of(QueryException $e): ?string
    {
        $state = $e->errorInfo[0] ?? $e->getCode();

        return is_string($state) ? $state : null;
    }
}
