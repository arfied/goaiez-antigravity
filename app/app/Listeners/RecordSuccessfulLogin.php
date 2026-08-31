<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Staff\StaffDirectory;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;

/**
 * Stamp `users.last_login_at`, however the person got in.
 *
 * On the event rather than in each controller, and that is the whole point:
 * there are four ways in — password, passkey, SSO, magic link — and two of them
 * (password and passkey) are handled entirely inside Fortify, where there is no
 * controller of ours to edit. Writing this at each call site would mean it was
 * correct for the two we own and quietly missing for the two we do not.
 *
 * Synchronous, not queued. It is one indexed UPDATE, and a queued version could
 * land after the next login on a busy account and record the older time.
 *
 * Deliberately does NOT touch the activity feed. The feed is the owner's record
 * of what the *system* did for them (`29` §2 rule 42); "you logged in" is
 * neither automated nor news. Internal-staff logins do belong in an append-only
 * record (`28` §9.1) — ✅ **that record now exists**, and it is `staff_events`
 * rather than `audit_log`, for the structural reason decision 741 gives.
 * `StaffDirectory` decides which accounts are ours; this listener owns *when*,
 * not *who*, so the rule has one home.
 */
final class RecordSuccessfulLogin
{
    public function __construct(private readonly StaffDirectory $staff) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // forceFill so this works regardless of what is fillable, and save()
        // rather than update() so no observer expecting a full model is skipped.
        $event->user->forceFill(['last_login_at' => Carbon::now()])->save();

        $this->staff->recordSignIn($event->user);
    }
}
