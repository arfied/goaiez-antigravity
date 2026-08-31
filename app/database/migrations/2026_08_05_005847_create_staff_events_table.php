<?php

declare(strict_types=1);

use App\Enums\StaffEvent;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to and by an internal account — `28` §9.1's last sentence.
 *
 * *"Every internal login and role change → `audit_log`."* ⚠️ **That cannot be
 * done, and the reason is structural rather than an omission** (decision 741).
 * `audit_log.business_id` is `NOT NULL`, the table is RLS-`ENABLE`+`FORCE`d on
 * `app.business_id`, and `AuditService::record()` opens with
 * `Tenancy::idOrFail()`. An internal staff member belongs to no business —
 * `UserRole` says so, `ResolveTenant` resolves nothing for them, and that is
 * deliberate — so there is no tenant to file either fact under. Filing it under
 * the subject's business is worse than not filing it: it would put our own
 * personnel record inside a customer's compliance trail.
 *
 * ⚠️ NOT TENANT-OWNED, AND UNLIKE `impersonation_sessions` (562) IT NAMES NO
 * TENANT AT ALL. This is `registry_changes`' shape (509) and takes its posture:
 * a platform-scoped append-only log, on the `TenancyTest` scope allowlist,
 * with a chokepoint lint naming `StaffDirectory` as its only reader and writer,
 * and the admin gate above it as what actually protects it — `legal_documents`'
 * answer (417–420).
 *
 * ⚠️ **ONE TABLE FOR BOTH HALVES OF THAT SENTENCE, NOT TWO** (decision 742).
 * Both rows are the same shape — an internal person, no tenant, append-only —
 * and they have one reader, the staff view of the audit explorer. Two tables
 * would be two panels answering one question, and an operator asking *"what has
 * this agent been doing"* would have to read them side by side and interleave
 * the timestamps by eye. The risk that buys — a store that grows a third
 * meaning, then a fourth, until it is `audit_log` without the tenant boundary —
 * is held by the enum being closed at two cases and the CHECK below refusing a
 * third at the database.
 *
 * ⚠️ **NO IP HASH, AND THAT IS A DECISION** (decision 750). A sign-in row could
 * carry `App\Support\HashedIp`, and nothing would read it: `28` §9.1's per-role
 * IP allowlist is not built (671), there is no new-device check, and no screen
 * shows it. A stored value nothing compares against is 256's vacuity wearing a
 * security hat, and the conservative choice under CLAUDE.md's "less stored PII"
 * is not to keep it. The column to add is the one the allowlist needs, on the
 * day the allowlist exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_events', function (Blueprint $table): void {
            $table->id();

            // A string cast to App\Enums\StaffEvent, never a database enum.
            $table->string('event');

            // ⚠️ WHO IT HAPPENED TO. A foreign key rather than a label, because
            // this end of the row is always a real account of ours — unlike
            // `actor`, which is sometimes the console. RESTRICTED rather than
            // cascading, for `impersonation_sessions`' reason (562): deleting a
            // staff member must not delete the record of the access they had.
            // It is also what makes `UserRole::None` necessary rather than
            // convenient — with this constraint in place a leaver cannot be
            // deleted, so revocation has to be expressible as a role.
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();

            // ⚠️ WHO DID IT, as a label and not a key — `audit_log.actor` and
            // `registry_changes.actor`'s convention, for their reason: not every
            // actor is a user. `console` is the artisan command that grants the
            // first role on a fresh install, and it belongs to nobody.
            // 'user:{id}' | 'console'. On a sign-in the actor *is* the subject,
            // which the CHECK below requires: the staff view filters "everything
            // agent X did" on this column alone, so a second vocabulary for
            // sign-ins would hide them from the search meant to find them.
            $table->string('actor');

            // Both sides, on `AuditService::recordChange()`'s rule: an entry
            // that records only the new value cannot answer what happened. Null
            // on a sign-in, where nothing moved.
            $table->string('role_before')->nullable();
            $table->string('role_after')->nullable();

            // ⚠️ REQUIRED ON A ROLE CHANGE, THOUGH `28` §9.1 DOES NOT ASK FOR
            // ONE (decision 749). §9.4 requires a typed reason to *read* one
            // customer's account for thirty minutes; granting somebody the
            // standing ability to do that, to every account, until revoked, is
            // the larger act. The CHECK below is what makes it true of a repair
            // script as well as of the screen.
            $table->string('reason', 500)->nullable();

            // Not nullable, unlike Laravel's own timestamps() default. Postgres
            // sorts NULL *first* on a DESC order (289), so one undated row in an
            // append-only trail sits above every dated one forever.
            $table->timestamp('created_at');
        });

        // The staff view's two reads: "everything this actor did" and "this
        // account's own history". Both are newest-first, which Blueprint cannot
        // express, and both are the whole reason the table has a screen.
        DB::statement(<<<'SQL'
            CREATE INDEX staff_events_actor_created_index
                ON staff_events (actor, created_at DESC)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX staff_events_subject_created_index
                ON staff_events (subject_user_id, created_at DESC)
        SQL);

        $events = collect(StaffEvent::cases())
            ->map(fn (StaffEvent $event): string => "'".$event->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE staff_events
                ADD CONSTRAINT staff_events_event_is_known
                CHECK (event IN ({$events}))
        SQL);

        $roles = collect(UserRole::cases())
            ->map(fn (UserRole $role): string => "'".$role->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE staff_events
                ADD CONSTRAINT staff_events_roles_are_known
                CHECK (
                    (role_before IS NULL OR role_before IN ({$roles}))
                AND (role_after  IS NULL OR role_after  IN ({$roles}))
                )
        SQL);

        // ⚠️ THE TWO EVENTS CARRY DIFFERENT COLUMNS, AND THE HALF-WRITTEN ROW IS
        // WHAT THIS REFUSES. A role change with no `role_after` is a row saying
        // somebody's authority moved and declining to say where to, which is the
        // one question it exists to answer; a sign-in carrying a reason is a row
        // written by code that thought it was doing something else.
        DB::statement(<<<'SQL'
            ALTER TABLE staff_events
                ADD CONSTRAINT staff_events_shape_matches_event
                CHECK (
                    CASE event
                        WHEN 'role_changed' THEN
                            role_after IS NOT NULL
                            AND role_after IS DISTINCT FROM role_before
                            AND reason IS NOT NULL
                            AND length(btrim(reason)) >= 10
                        WHEN 'signed_in' THEN
                            role_before IS NULL
                            AND role_after IS NULL
                            AND reason IS NULL
                            AND actor = 'user:' || subject_user_id::text
                        ELSE false
                    END
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_events');
    }
};
