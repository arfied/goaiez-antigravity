<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Who may reach the support console.
 *
 * ⚠️ **A second gate rather than a widened `AdminAccess`**, and the split is the
 * point. Every screen under `/admin` today is platform-global — plan prices,
 * legal documents, the defaults registry — so admitting `28` §9.1's five
 * internal roles through that one gate would hand a `cs_readonly` on their
 * first week the ability to edit what customers are charged. `AdminAccess`
 * therefore kept its narrow meaning (`canAdministerPlatform()`) and the console
 * sections a support person may reach admit them section by section.
 *
 * That is more gates, deliberately. `28` §9.2's whole navigation is
 * role-filtered, so the alternative — one gate for "internal" with per-screen
 * role comparisons inside — puts an authorization decision in every component's
 * `mount()`, which is the shape `AdminAccess`'s own docblock exists to refuse.
 */
final class SupportAccess
{
    public const GATE = 'access-support';

    public static function register(): void
    {
        Gate::define(self::GATE, static function (User $user): bool {
            // Asks whether the person can do anything here, not which role they
            // hold. A role that cannot open a session of either strength has no
            // business on the screen whose only actions open one — `cs_readonly`
            // and `billing_admin` are platform staff and are not support.
            return $user->role->strongestImpersonationMode() !== null;
        });
    }
}
