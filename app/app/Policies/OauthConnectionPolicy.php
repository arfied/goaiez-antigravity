<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OauthConnection;
use App\Models\User;

/**
 * Who may hand the platform a credential that acts as the business.
 *
 * Narrower than automation settings on purpose. Connecting Google or Microsoft
 * gives the platform the ability to post as the business and send as its
 * mailbox; that belongs with the people who own the relationship, not with
 * everyone who can sign in.
 *
 * No token ever reaches a view, so `view` here governs the *existence and
 * health* of a connection — "Google: connected, needs reconnecting" — never its
 * contents. The model's $hidden and the vault's explicit encryption are what
 * enforce that; a policy could not.
 *
 * As in AutopilotSettingsPolicy, the tenant is not checked here. A policy
 * answers "may this role do this?"; the global scope and row-level security
 * answer "is this row yours?", and conflating the two makes the weaker one look
 * like the boundary.
 */
final class OauthConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OauthConnection $connection): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageConnections();
    }

    public function update(User $user, OauthConnection $connection): bool
    {
        return $user->role->canManageConnections();
    }

    /**
     * Disconnecting is as consequential as connecting: it stops every automation
     * that depends on the provider.
     */
    public function delete(User $user, OauthConnection $connection): bool
    {
        return $user->role->canManageConnections();
    }
}
