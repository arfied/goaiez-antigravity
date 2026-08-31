<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Who may reach /admin.
 *
 * One gate, in one place. Screens and the nav ask this ability; none of them
 * compares a role, so there is exactly one line here rather than one per screen.
 *
 * It shipped denying everyone while FOUND-04 was still in flight, which was the
 * right posture for an unfinished authorization surface — a permissive
 * placeholder on an admin panel is the kind of thing that ships, because nothing
 * fails while it is wrong. Now wired to the real predicate.
 */
final class AdminAccess
{
    public const GATE = 'access-admin';

    public static function register(): void
    {
        Gate::define(self::GATE, static function (User $user): bool {
            // Asks what the role may *do*, never which role it is. An agency
            // manages tenants; it is not the platform, and that distinction is
            // the difference between one customer's data and everyone's.
            //
            // ⚠️ canAdministerPlatform(), not isPlatformStaff(), and the two
            // stopped being the same question when `28` §9.1's five internal
            // roles landed. Every screen under /admin today is a platform-global
            // one — prices, legal documents, the settings registry — so the
            // narrow predicate is the one that keeps this gate meaning what it
            // meant when there was a single internal role. A console section a
            // support agent may reach admits them on the section, never by
            // widening this line.
            return $user->role->canAdministerPlatform();
        });
    }
}
