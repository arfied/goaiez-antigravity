<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\StaffSessionEnd;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The one place this application decides how long an internal session lives
 * (`28` §9.1: *"session lifetime 12h, idle timeout 30m"*).
 *
 * `SecondFactor`'s sibling, and deliberately shaped like it: the questions live
 * together because they disagreed while they were spread across two controllers
 * and a config file, and neither disagreement had a symptom.
 *
 * ## ⚠️ Why this was not built with the rest of `28` §9.1 (decision 670)
 *
 * It reads as a forty-line middleware and is not one. Both hand-rolled sign-in
 * routes passed `remember: true` **unconditionally**, so a session that hit any
 * ceiling was silently re-authenticated from the cookie into a brand new
 * session — and any absolute cap measured from the start of a session restarts
 * with it. A twelve-hour ceiling written on top of that is a control that reads
 * as enforced, tests green on a clock nobody advances far enough, and never
 * fires. Decision 256's vacuity with a clock on it.
 *
 * ⚠️ **And 670 named two sites where there were three.** `SecondFactor::
 * challenge()` stashed `login.remember => true` for Fortify's own two-factor
 * controller to read — which is the site that mattered, because §9.1 mandates a
 * second factor for *every* internal account, so **every staff sign-in on those
 * two routes goes through the challenge** and completes inside Fortify rather
 * than in our controller. Fixing only the two named sites would have left
 * remember-me alive for exactly the population the cap applies to, with both
 * named sites visibly corrected. That is the shape this file exists to stop.
 *
 * ## The answer to what remember-me means for an internal account: it does not
 *
 * `remember()` is the chokepoint, and it is the whole of the policy. A
 * remember-me cookie is a bearer credential with a five-year default lifetime
 * that survives the browser closing, and it is issued to the device rather than
 * to the person — which is a reasonable trade for a business owner checking
 * their reviews and the wrong one for an account that can read every tenant's
 * compliance record. `28` §9.1 asks for a twelve-hour ceiling and a thirty-
 * minute idle timeout on those accounts; a credential that outlives both is not
 * compatible with either.
 *
 * **Tenants keep it, unchanged.** Nothing in `29` asks for a ceiling on an
 * owner's session, and `CLAUDE.md` forbids a tenant-facing toggle in its place.
 *
 * ## ⚠️ The rule is enforced at runtime, not asserted about call sites
 *
 * `remember()` at three call sites is a rule people follow. What makes the cap
 * true is `expiry()` refusing any staff session that arrived `viaRemember()` —
 * so a fourth sign-in route written next year that passes `remember: true` out
 * of habit produces a staff session that is refused on its first request rather
 * than a ceiling that silently stops firing. Decision 661's lesson applied to a
 * different door: the stricter the requirement, the more attractive the way
 * around it, so the requirement is placed where the way around it also lands.
 *
 * ## ⚠️ The idle timeout is not `SESSION_LIFETIME`
 *
 * The tempting implementation is one config line. `session.lifetime` is 120
 * minutes and **global**, so moving it to 30 would sign every tenant out four
 * times as often to satisfy a rule about internal accounts — a product change
 * bought by accident. The clock lives in the session payload instead, which is
 * per-account by construction and leaves every tenant on 120 exactly as before.
 */
final class StaffSession
{
    /**
     * `28` §9.1's *"session lifetime 12h"*, in minutes.
     *
     * A constant rather than a `platform_settings` key. §9.1 makes the IP
     * allowlist configurable and names these two as flat requirements, and a
     * registry row would let an operator lengthen a security ceiling from a
     * screen with no second pair of eyes on it. Decision 511's own boundary:
     * this is a cap that is not a price.
     */
    public const ABSOLUTE_MINUTES = 720;

    /** `28` §9.1's *"idle timeout 30m"*, in minutes. */
    public const IDLE_MINUTES = 30;

    /**
     * When the session that is open now was opened.
     *
     * ⚠️ Stamped by the middleware on the first request it sees rather than by
     * the login routes, and that is not laziness. Four ways in reach this
     * application and **two of them complete inside Fortify** — `POST /login`
     * and the two-factor challenge — where there is no controller of ours to
     * edit. A stamp written at the sites we own would leave the ceiling
     * unmeasured on the one route every internal account is required to finish
     * through, which is decision 661's hole rebuilt in a different column.
     * Stamping where the ceiling is *read* means every door is covered by
     * construction, at a cost of the milliseconds between the sign-in redirect
     * and the request that follows it.
     */
    private const STARTED_AT = 'staff.session_started_at';

    /** The last request this session made. */
    private const SEEN_AT = 'staff.last_seen_at';

    /**
     * Whether these rules apply to this person at all.
     *
     * `isPlatformStaff()` — population, not power (decision 560), the same
     * predicate and the same reason as `SecondFactor::required()`. §9.1 says
     * *every internal account*, so a `cs_readonly` on their first week is
     * covered exactly as a `super_admin` is.
     *
     * ⚠️ **The `instanceof` guard is load-bearing on the login path**, not
     * defensive habit. `users.role` is populated by the migration's column
     * default, so a just-`create()`d model carries no role in memory — the
     * state every first-time SSO signup is in on the exact request that calls
     * `remember()` below. Decision 753 is that 500, and the note it ends on is
     * that anything new reading a role on the login path needs this guard.
     * Without one this file would break the Google callback again.
     */
    public static function applies(?User $user): bool
    {
        return $user?->role instanceof UserRole && $user->role->isPlatformStaff();
    }

    /**
     * Whether a remember-me cookie may be issued for this sign-in.
     *
     * The chokepoint. Every site that signs somebody in — ours directly, and
     * Fortify's through the `login.remember` key `SecondFactor::challenge()`
     * stashes — asks this rather than deciding for itself.
     */
    public static function remember(?User $user): bool
    {
        return ! self::applies($user);
    }

    /**
     * Why this session must end now, or null if it may continue.
     *
     * Checked in the order the reasons *supersede* each other rather than in
     * the order they were written: a remembered session has no honest clock to
     * read, and the ceiling outranks the idle timer because a session that is
     * both over twelve hours old and idle reached the ceiling first.
     */
    public static function expiry(Request $request, ?User $user): ?StaffSessionEnd
    {
        if (! self::applies($user)) {
            return null;
        }

        // ⚠️ THE REFUSAL THAT MAKES THE OTHER TWO NON-VACUOUS (670). A session
        // resurrected from a cookie is a *new* session — new id, empty payload,
        // and therefore a ceiling that starts over. Read as a clock it is
        // indistinguishable from an honest sign-in one second ago, so there is
        // nothing here to measure and the only correct answer is to refuse it.
        if (Auth::viaRemember()) {
            return StaffSessionEnd::Remembered;
        }

        $session = $request->session();

        $startedAt = $session->get(self::STARTED_AT);

        if (is_int($startedAt) && (self::now() - $startedAt) >= self::ABSOLUTE_MINUTES * 60) {
            return StaffSessionEnd::Absolute;
        }

        $seenAt = $session->get(self::SEEN_AT);

        if (is_int($seenAt) && (self::now() - $seenAt) >= self::IDLE_MINUTES * 60) {
            return StaffSessionEnd::Idle;
        }

        return null;
    }

    /**
     * Start the clock if it is not running, and record this request against it.
     *
     * ⚠️ `STARTED_AT` is written **only when absent**, which is what makes it a
     * ceiling rather than a rolling window. Writing it unconditionally is the
     * one-character version of this method that passes every test which does
     * not advance the clock past twelve hours, and it turns the absolute cap
     * into a second idle timer.
     */
    public static function touch(Request $request, ?User $user): void
    {
        if (! self::applies($user)) {
            return;
        }

        $session = $request->session();

        if (! is_int($session->get(self::STARTED_AT))) {
            $session->put(self::STARTED_AT, self::now());
        }

        $session->put(self::SEEN_AT, self::now());
    }

    /**
     * The current time, as an integer Unix timestamp.
     *
     * ⚠️ **`now()`, never PHP's `time()`, and the difference is the whole
     * testability of this file.** Laravel's `travel()` moves Carbon's clock and
     * has no effect on `time()` — so a version of this class written the obvious
     * way cannot be tested at all without a twelve-hour sleep, and the ceiling
     * would ship pinned by nothing. That is decision 256 arriving by way of a
     * function name: the code would be correct, and the only tests anybody could
     * write against it would be ones it passes with every clock deleted.
     *
     * Stored as an `int` rather than a datetime string because a session payload
     * is serialised and a raw timestamp cannot be misparsed by a timezone.
     */
    private static function now(): int
    {
        return now()->getTimestamp();
    }
}
