<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| **The channel name is not authorization.** A client chooses what it subscribes
| to, so `business.7` is attacker-controlled input and these callbacks are the
| only enforcement. A cross-tenant subscription is a Blocker, not a bug.
|
| Tested in the isolation suite rather than only here: a user from one business
| must fail authorization for another's channel.
|
*/

Broadcast::channel('business.{businessId}', function (User $user, string $businessId): bool {
    // Self-sufficient rather than relying on ResolveTenant having run. The
    // broadcasting auth route does sit in the web group today, so in practice it
    // has — but this callback IS the boundary, and a boundary that depends on
    // middleware ordering elsewhere is one route registration away from being
    // silently unenforced. Without app.user_id the owner_lookup policy matches
    // nothing and every subscription would be refused, which fails closed but
    // for the wrong reason.
    Tenancy::setUser((int) $user->getKey());

    // Resolved through owner_user_id rather than trusting the id in the name.
    // withoutGlobalScopes because channel authorization runs outside a resolved
    // tenant — this is the same bootstrap problem ResolveTenant has, and the
    // same answer: the database's owner_lookup policy still refuses another
    // owner's row, so the query cannot return one no matter what it asks for.
    //
    // Explicit boolean, never a truthy value: Broadcast::channel treats any
    // non-false return as authorized, so returning a model here would grant
    // access to anyone whose lookup found anything at all.
    return Business::withoutGlobalScopes()
        ->whereKey((int) $businessId)
        ->where('owner_user_id', $user->getKey())
        ->exists();
});
