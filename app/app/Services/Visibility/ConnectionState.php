<?php

declare(strict_types=1);

namespace App\Services\Visibility;

/**
 * Whether a tenant's Search Console grant exists and works.
 *
 * Three cases rather than a boolean, for decision 1084's reason one level down:
 * "never connected" and "connected and since broken" are the same `false` to a
 * predicate and two different sentences to an owner. The first asks them to do
 * something new; the second tells them something they set up has stopped, which
 * is the message they are owed *before* they notice the numbers stopped moving.
 *
 * Lives beside the reading rather than in `app/Enums` because it is an internal
 * distinction of this subsystem — nothing persists it, nothing casts a column to
 * it, and no request validates against it.
 */
enum ConnectionState
{
    /** No `oauth_connections` row for `OauthProvider::Gsc`. */
    case Absent;

    /** A row, and `TokenService` would hand out a token for it. */
    case Usable;

    /**
     * A row whose status is not Active — expired or revoked.
     *
     * ⚠️ `TokenService::markUnusable()` writes `Expired` even when the cause was
     * a revocation, because Google returns `invalid_grant` for both and the
     * remedy is identical. So this case means "reconnect", never "we know which
     * of the two happened".
     */
    case Unusable;
}
