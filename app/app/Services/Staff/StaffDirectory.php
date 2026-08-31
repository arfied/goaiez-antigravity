<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\StaffEvent;
use App\Enums\UserRole;
use App\Exceptions\StaffChangeRefused;
use App\Models\StaffEventRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who works here, and what they may do — `28` §9.1, §9.2's *"internal users &
 * roles"*.
 *
 * ⚠️ **THE ONLY WRITER OF `users.role` IN THIS APPLICATION, AND UNTIL THIS
 * SLICE THERE WAS NONE** (decision 740). `28` §9.1's five internal roles landed
 * with the console, the support gate, the impersonation modes and the audit
 * explorer built on top of them, and nothing could put one on a user: the only
 * value that column had ever held was the migration's `default('owner')`. So
 * `super_admin` was unreachable too, and the whole Ops Console was openable by
 * nobody on a fresh install. An `ArchitectureTest` lint holds the writer here,
 * and `User`'s `#[Fillable]` attribute — which omits `role` — is the layer
 * beneath it: mass assignment cannot reach the column even from this file.
 *
 * ⚠️ **AND THE ONLY READER OR WRITER OF `staff_events`**, held by a second lint,
 * for `ImpersonationSession`'s reason (624): that table has no RLS predicate
 * that refuses anybody, so the chokepoint is the whole protection.
 *
 * ## What it refuses, and why each refusal is a lockout rather than a validation
 *
 * Because this is the only writer, a change it permits is one nothing else in
 * the application can undo. That inverts the usual bias: a refusal here costs an
 * operator a second attempt, and a permission costs a database edit on a live
 * system. All five are `StaffChangeRefused`, all five are tested, and the two
 * that matter most are the last two.
 *
 * ## What it deliberately does not check
 *
 * ⚠️ **It cannot ask whether the subject owns a business, and does not try**
 * (decision 746). `businesses` is RLS-`FORCE`d on `app.business_id` and
 * `app.user_id`; the operator running this screen is a platform admin with
 * neither, so `$subject->ownedBusinesses()` returns zero rows for a tenant owner
 * and zero rows for a staff member alike — an answer that is indistinguishable
 * from the truth and wrong half the time. That is decision 569's wall, met for
 * the fourth time. The available signal is the role the subject already holds,
 * and it is enough: every tenant user has a tenant role, because `owner` is the
 * column's default and this service will not write one.
 */
final class StaffDirectory
{
    /**
     * ⚠️ Off for exactly one caller, and the alternative was a lie in the log.
     *
     * `Impersonation::stop()` calls `Auth::login($agent)` to hand a support
     * agent back their own account at the end of a session (562). That fires
     * `Login` like any other, so without this every support session would close
     * with a row saying the agent *signed in* — an event that did not happen,
     * on the one screen an auditor reads to find out what did. Inflating a
     * security log with false entries is worse than a thinner log, because the
     * reader cannot tell which rows to trust.
     *
     * `Model::withoutEvents()`' shape: scoped to a closure and restored in a
     * `finally`, so an exception inside cannot leave sign-in recording off for
     * the rest of the process.
     */
    private static bool $recordingSignIns = true;

    /**
     * Run something whose `Login` event is not a sign-in.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutSignInRecord(callable $callback): mixed
    {
        self::$recordingSignIns = false;

        try {
            return $callback();
        } finally {
            self::$recordingSignIns = true;
        }
    }

    /**
     * Every internal account, including revoked ones.
     *
     * Revoked accounts stay in the list on purpose. A leaver who is not on the
     * screen is a leaver nobody can confirm was revoked, and re-granting somebody
     * who came back has to be possible without a database edit — which is the
     * defect this whole service exists to close, rebuilt one screen down.
     *
     * @return Collection<int, User>
     */
    public function staff(): Collection
    {
        $internal = collect(UserRole::cases())
            ->filter(fn (UserRole $role): bool => self::isInternalAccountRole($role))
            ->map(fn (UserRole $role): string => $role->value)
            ->values()
            ->all();

        return User::query()
            ->whereIn('role', $internal)
            ->orderBy('name')
            // Ties on name broken by id, not by created_at: that column is
            // nullable on this table too (289), and a stable order is what stops
            // two rows swapping places between renders of the same list.
            ->orderBy('id')
            ->get();
    }

    /**
     * Create an internal account and record the grant.
     *
     * ⚠️ **NO PASSWORD IS SET, AND NOTHING HERE CAN SET ONE** (decision 747).
     * An operator minting a password for somebody else has to transmit it, and
     * every channel to hand is worse than the account having none: the person's
     * first credential would exist in a chat log, and the account it opens holds
     * standing access to every customer's data. Three of this application's four
     * ways in never establish a password anyway (`User`'s docblock), so a
     * password-less account is the normal case rather than a broken one — they
     * ask for a sign-in link, and `RequiresTwoFactor` puts them on the enrolment
     * screen before they reach anything (slice 3).
     */
    public function create(
        string $name,
        string $email,
        UserRole $role,
        string $reason,
        User $actor,
    ): User {
        return $this->createAs($name, $email, $role, $reason, self::labelFor($actor));
    }

    /**
     * Create an internal account from the console, with no operator behind it.
     *
     * ⚠️ **THE IGNITION, AND THE SLICE DOES NOT WORK WITHOUT IT** (decision 748).
     * The screen above this service is gated on `AdminAccess::GATE`, which is
     * `super_admin` alone — so on a fresh install, where no account holds any
     * internal role at all, the screen that grants the first one cannot be
     * opened by anybody. A console command is the only door that is not behind
     * the door it opens.
     */
    public function createFromConsole(string $name, string $email, UserRole $role, string $reason): User
    {
        return $this->createAs($name, $email, $role, $reason, 'console');
    }

    /**
     * Move somebody's role, and record both sides of it with the reason given.
     */
    public function assign(User $subject, UserRole $to, string $reason, User $actor): StaffEventRecord
    {
        // ⚠️ NOBODY CHANGES THEIR OWN ROLE (decision 745), and the risk runs in
        // both directions. Upward it is self-escalation with an audit row
        // attributing it to the person who benefited. Downward it is the last
        // `super_admin` demoting themselves out of the only screen that could
        // put them back — the refusal below catches that specific case, and this
        // one catches it before the count is even asked.
        if ($subject->getKey() === $actor->getKey()) {
            throw StaffChangeRefused::because(
                'You cannot change your own role. Ask another super admin to make this change.'
            );
        }

        return $this->assignAs($subject, $to, $reason, self::labelFor($actor));
    }

    /**
     * Move somebody's role from the console.
     *
     * The self-change refusal does not apply: there is no operator to be the
     * same person as, and the console is how a locked-out platform is opened.
     */
    public function assignFromConsole(User $subject, UserRole $to, string $reason): StaffEventRecord
    {
        return $this->assignAs($subject, $to, $reason, 'console');
    }

    /**
     * Record that an internal account signed in — `28` §9.1's other half.
     *
     * Called from `RecordSuccessfulLogin`, on the `Login` event, so all four
     * ways in are covered by one call: two of them happen entirely inside
     * Fortify where there is no controller of ours to edit, which is decision
     * 661's finding pointed at a record rather than at a control.
     *
     * ⚠️ **A revoked account's sign-in is recorded too**, though `none` is not a
     * platform-staff role. `none` is only ever reached *from* an internal role,
     * so it is one of our accounts with its access removed — and somebody
     * continuing to sign in after being revoked is the single most interesting
     * row this table can hold. Excluding it would drop exactly the case the log
     * is worth having for.
     */
    public function recordSignIn(User $user): void
    {
        if (! self::$recordingSignIns) {
            return;
        }

        // ⚠️ `$user->role` IS ABSENT MORE OFTEN THAN `User`'s PROPERTY DOCBLOCK
        // SUGGESTS, AND THIS IS THE PATH WHERE IT HAPPENS. The column's value
        // comes from the migration's `default('owner')`, so a model that was
        // just `create()`d carries no role in memory until something refreshes
        // it — which is exactly the state a first-time SSO signup is in when
        // `Auth::login()` fires. It broke twelve tests on first run.
        //
        // Read through `getAttribute()` rather than the declared property on
        // purpose. `@property UserRole $role` is true of every *hydrated* model
        // and Larastan believes it, so `$user->role instanceof UserRole` is
        // reported as always true and the guard reads as dead code. Widening
        // the property to `?UserRole` would be worse: it would force a null
        // check into eight policies and gates that only ever receive a hydrated
        // user, to describe a window that exists on one request.
        // `FortifyServiceProvider` guards both its gates with `instanceof
        // UserRole` for the same underlying reason.
        $role = $user->getAttribute('role');

        if (! $role instanceof UserRole || ! self::isInternalAccountRole($role)) {
            return;
        }

        StaffEventRecord::query()->create([
            'event' => StaffEvent::SignedIn,
            'subject_user_id' => $user->getKey(),
            // ⚠️ THE ACTOR IS THE SUBJECT, DELIBERATELY, AND A CHECK PINS IT.
            // A sign-in is something a person does to their own account, so
            // `self` reads better in isolation and would break the one thing the
            // store is read through: the staff view filters *"everything agent X
            // did"* on `actor`, and a second vocabulary would hide every sign-in
            // from the search that exists to find them.
            'actor' => self::labelFor($user),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Everything an actor did here, newest first — the staff view's third panel.
     *
     * @return LengthAwarePaginator<int, StaffEventRecord>
     */
    public function eventsBy(?string $actor, int $perPage): LengthAwarePaginator
    {
        return StaffEventRecord::query()
            ->with('subject')
            ->when($actor !== null, fn ($query) => $query->where('actor', $actor))
            // `id` rather than `created_at`, on decision 631's reasoning: in an
            // append-only log the id *is* the order things happened in, and it
            // cannot tie.
            ->orderByDesc('id')
            ->paginate($perPage, pageName: 'staffEvents');
    }

    /**
     * Whether this role belongs to one of our own accounts.
     *
     * Not a predicate on `UserRole`, deliberately. Decision 560 records what
     * happens when internal-population questions multiply on that enum: there
     * were two of them, they looked like one, and the one that meant "is this
     * person one of ours" ended up gating Horizon's dashboard. This one has a
     * single caller — this class — and belongs to it.
     */
    private static function isInternalAccountRole(UserRole $role): bool
    {
        return $role->isPlatformStaff() || $role === UserRole::None;
    }

    private static function labelFor(User $actor): string
    {
        return 'user:'.$actor->getKey();
    }

    private function createAs(
        string $name,
        string $email,
        UserRole $role,
        string $reason,
        string $actor,
    ): User {
        $this->guardTargetRole($role);
        $this->guardReason($reason);

        return DB::transaction(function () use ($name, $email, $role, $reason, $actor): User {
            $user = new User;

            // forceFill because `role` is deliberately absent from `User`'s
            // `#[Fillable]` list, so the column is unreachable by mass
            // assignment even from the one class allowed to write it.
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password' => null,
            ])->save();

            $this->record(
                subject: $user,
                actor: $actor,
                before: null,
                after: $role,
                reason: $reason,
            );

            return $user;
        });
    }

    private function assignAs(User $subject, UserRole $to, string $reason, string $actor): StaffEventRecord
    {
        $from = $subject->role;

        $this->guardTargetRole($to);
        $this->guardReason($reason);

        if ($from === $to) {
            throw StaffChangeRefused::because(
                $subject->name.' already holds that role. Nothing was changed.'
            );
        }

        // ⚠️ A TENANT USER IS NOT THIS SCREEN'S TO MOVE (decision 746). Demoting
        // a business owner from the internal console would strip them of
        // authority inside their own account — every tenant-facing policy asks
        // `$user->role`, so `owner` → `cs_readonly` locks a paying customer out
        // of their own settings, from a screen whose subject list is meant to be
        // our own staff. `owner` is the column's default, so this also refuses
        // every account that has never been anything.
        if ($from->isTenantRole()) {
            throw StaffChangeRefused::because(
                $subject->name.' holds a tenant role ('.$from->label().'). Roles inside a '
                .'customer account are not changed from here.'
            );
        }

        // ⚠️ THE LAST SUPER ADMIN CANNOT BE DEMOTED (decision 744), and this is
        // the refusal that would be missed. `canAdministerPlatform()` is
        // `super_admin` alone (560), this service is the only writer of the
        // column, and its screen is behind that same ability — so demoting the
        // last one closes the door from the inside on a running platform. The
        // console command is the way back, and it needs shell access to the
        // production box, which is not a thing an operator has at the moment
        // they discover they need it.
        if ($from === UserRole::SuperAdmin && $to !== UserRole::SuperAdmin && $this->superAdminCount() <= 1) {
            throw StaffChangeRefused::because(
                'This is the only super admin. Give somebody else that role first, or '
                .'the platform console cannot be opened by anybody.'
            );
        }

        return DB::transaction(function () use ($subject, $from, $to, $reason, $actor): StaffEventRecord {
            $subject->forceFill(['role' => $to])->save();

            return $this->record(
                subject: $subject,
                actor: $actor,
                before: $from,
                after: $to,
                reason: $reason,
            );
        });
    }

    /**
     * ⚠️ The role write and its record move together or neither does — decision
     * 518, one branch after it was written and for the same reason. A failing
     * insert here leaving the role moved is invisible: the person has the access
     * and nothing says who gave it to them, which is precisely the state `28`
     * §9.1's sentence exists to make impossible.
     */
    private function record(
        User $subject,
        string $actor,
        ?UserRole $before,
        UserRole $after,
        string $reason,
    ): StaffEventRecord {
        return StaffEventRecord::query()->create([
            'event' => StaffEvent::RoleChanged,
            'subject_user_id' => $subject->getKey(),
            'actor' => $actor,
            'role_before' => $before,
            'role_after' => $after,
            'reason' => trim($reason),
            'created_at' => Carbon::now(),
        ]);
    }

    private function guardTargetRole(UserRole $role): void
    {
        if ($role->isTenantRole()) {
            throw StaffChangeRefused::because(
                $role->label().' is a role inside a customer account, not an internal one. '
                .'The internal console does not hand out authority in a tenant.'
            );
        }
    }

    /**
     * The database enforces this too, and the duplication is the point (216,
     * 303–316): the CHECK catches the repair script, and this catches it early
     * enough to name the field rather than raise a SQLSTATE at an operator.
     */
    private function guardReason(string $reason): void
    {
        $length = mb_strlen(trim($reason));

        if ($length < 10) {
            throw StaffChangeRefused::because(
                'Say why, in a sentence. This is the record of who was given access to '
                .'every customer account, and by whom.'
            );
        }

        // The screen validates this too. This is here for the console command,
        // which has no form in front of it — without it a long reason reaches
        // the column and fails at 22001, which is a stack trace where a sentence
        // belongs.
        if ($length > 500) {
            throw StaffChangeRefused::because(
                'That reason is longer than 500 characters. This record is append-only, '
                .'so keep it to the sentence somebody will read.'
            );
        }
    }

    private function superAdminCount(): int
    {
        return User::query()->where('role', UserRole::SuperAdmin->value)->count();
    }
}
