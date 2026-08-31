<?php

declare(strict_types=1);

namespace App\Services\Impersonation;

use App\Enums\AutopilotActionType;
use App\Enums\ImpersonationCapability;
use App\Enums\ImpersonationEnding;
use App\Enums\ImpersonationMode;
use App\Enums\SupportWriteSubject;
use App\Exceptions\ImpersonationRefused;
use App\Models\Business;
use App\Models\ImpersonationSession;
use App\Models\User;
use App\Notifications\SupportSessionSummary;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Export\ExportBuilder;
use App\Services\Mail\PlatformMailer;
use App\Services\Staff\StaffDirectory;
use App\Support\Impersonation\ReadOnlyConnection;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Session\Session as SessionStore;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Support inside a customer's account — the only place that opens one, closes
 * one, or answers whether one is open (`28` §9.4).
 *
 * ## Why the acting identity is the owner
 *
 * Starting a session logs the agent in **as the business owner** and remembers
 * the agent in the session store. That reads alarming and is the safer of the
 * two designs, for a reason specific to what view mode is for: it exists so
 * that "I cannot reproduce what you are seeing" stops being an answer, and that
 * is only true if the render is *identical* to the owner's. The alternative —
 * keep the agent authenticated and special-case the tenant — means every
 * policy, every `@can`, and every role predicate in the application answers a
 * question about a `support_agent` who belongs to no business, so the screen
 * the agent is looking at is not the screen the owner has. A support tool that
 * shows you something slightly different from what the customer sees is worse
 * than none, because it produces confident wrong answers.
 *
 * `28`'s "never the owner's real session, never their password" is honoured
 * literally: no cookie of theirs is used, no credential of theirs is read, and
 * the session is a fresh one bound to a row that expires. What it forbids is
 * borrowing their *credentials*, not rendering as them.
 *
 * ⚠️ **The row is the authority; the session store is only a pointer.** Every
 * request re-reads the row and re-checks it (`Impersonating` middleware). That
 * ordering is what makes `28` §9.4's fourth build-failing test true — an
 * expired or revoked session is dead on the next request rather than whenever
 * a sweep runs — and it is why nothing here caches.
 *
 * ## What this class deliberately does not do
 *
 * - ~~**Owner notification email.**~~ ✅ **Built (703).** 571 left this a seam
 *   for want of an email layer; there is one now, and `close()` sends
 *   `SupportSessionSummary` through `PlatformMailer`. ⚠️ **Act-as only.** `28`
 *   §9.4 says view-only sessions notify *"only if the tenant enabled strict
 *   mode"* — there is no strict mode, no column holding one, and `CLAUDE.md`
 *   forbids adding a tenant-facing toggle to invent one. So view-only stays
 *   silent, which is the document's own default rather than a narrowing.
 * - **PHI masking.** `28` §9.4 masks message bodies for PHI-classified tenants
 *   and forbids offshore roles from impersonating them at all. PHI isolation is
 *   being built separately and owns `DataClassification`; this class leaves the
 *   seam rather than guessing at half of it — see `refuseIfUnreachable()`.
 * - **Mandatory 2FA and the idle timeout** for internal accounts (`28` §9.1).
 *   An auth-surface change, not this one's.
 */
final class Impersonation
{
    /**
     * Where the pointer lives in the agent's own session.
     *
     * Two keys, not one. The session id says which row; the agent id says whose
     * login to give back when it ends, and it has to be stored rather than
     * derived because by then `Auth::id()` is the owner's.
     */
    public const SESSION_KEY = 'impersonation.session_id';

    public const AGENT_KEY = 'impersonation.agent_id';

    /**
     * The floor on a typed reason.
     *
     * ⚠️ Short enough to be no obstacle and long enough that "test", "asdf" and
     * "." do not clear it — which is the entire ambition. A length check cannot
     * make a reason meaningful and should not pretend to; what it does is stop
     * the field being dismissed with one keystroke, so that the person typing
     * has to have a sentence in mind. The database enforces the same number.
     */
    public const MINIMUM_REASON = 10;

    public function __construct(
        private readonly AuditService $audit,
        private readonly ActivityService $activity,
        private readonly SessionStore $session,
        private readonly PlatformMailer $mailer,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * Open a session, or refuse and say why.
     *
     * @throws ImpersonationRefused
     */
    public function start(
        User $agent,
        Business $business,
        ImpersonationMode $mode,
        string $reason,
        ?string $ticketRef = null,
    ): ImpersonationSession {
        $reason = trim($reason);

        $this->refuseIfRoleCannot($agent, $mode);
        $this->refuseIfReasonIsThin($reason);
        $this->refuseIfTicketMissing($mode, $ticketRef);
        $this->refuseIfUnreachable($business);

        $owner = User::query()->find($business->owner_user_id);

        if (! $owner instanceof User) {
            // `businesses.owner_user_id` is NOT NULL and cascades from `users`,
            // so this is a torn row rather than an ordinary state. It is still
            // a refusal rather than an assumption: the alternative to failing
            // here is choosing somebody to sign in as, and there is no safe
            // way to choose.
            throw ImpersonationRefused::because(
                'This account has no owner on record, so there is nobody to sign in as. Escalate it.'
            );
        }

        if ($this->currentSessionId() !== null) {
            throw ImpersonationRefused::because(
                'You are already inside an account. End that session before starting another.'
            );
        }

        try {
            $session = ImpersonationSession::create([
                'agent_id' => $agent->id,
                'business_id' => $business->id,
                'mode' => $mode,
                'reason' => $reason,
                'ticket_ref' => $ticketRef,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($mode->ttlMinutes()),
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            // The partial unique index on (business_id) WHERE ended_at IS NULL.
            // Checking first and inserting second would be a race, and two
            // agents clicking within the same second is ordinary on a busy
            // desk — so the database decides and this translates the SQLSTATE
            // into something an agent can act on.
            if (str_contains($e->getMessage(), 'impersonation_sessions_one_live_per_business')) {
                throw ImpersonationRefused::because(
                    'Another support session is already open on this account. Only one of us can be inside at a time.'
                );
            }

            throw $e;
        }

        // Both records are written against the tenant, because that is who they
        // are about — an auditor asking "who has been inside this business's
        // account" reads the tenant's own log, not a platform one.
        Tenancy::actingAs($business->id, function () use ($session, $agent, $business, $mode, $reason, $ticketRef): void {
            $this->audit->record(
                action: 'impersonation.started',
                actor: $session->auditActor(),
                entity: $business,
                metadata: [
                    'mode' => $mode->value,
                    'reason' => $reason,
                    'ticket_ref' => $ticketRef,
                    'expires_at' => $session->expires_at->toIso8601String(),
                    'agent_role' => $agent->role->value,
                ],
            );

            if ($mode === ImpersonationMode::Act) {
                $this->activity->record(
                    action: AutopilotActionType::SupportSessionOpened,
                    metadata: ['ticket_ref' => $ticketRef],
                );
            }
        });

        // Order matters: the pointer is written *after* the row exists, so a
        // failed insert cannot leave a session key aimed at nothing.
        $this->session->put(self::AGENT_KEY, $agent->id);
        $this->session->put(self::SESSION_KEY, $session->id);

        // ⚠️ Not a sign-in either, and the case that makes it reachable is
        // decision 621's: a staff member who also owns a business. Impersonating
        // that account logs in *as them*, and an unsuppressed `Login` would file
        // a row saying they signed in when somebody else opened their account.
        StaffDirectory::withoutSignInRecord(fn () => Auth::login($owner));

        // A fresh session id on the way in and on the way out. Without it the
        // same cookie carries the agent's pre-impersonation session and the
        // owner's, which is the fixation shape Laravel regenerates for on every
        // ordinary login and this one is not ordinary.
        $this->session->migrate(destroy: false);

        return $session;
    }

    /**
     * The record of where staff have been — `28` §14.1's "everything agent X did".
     *
     * ⚠️ **This lives here rather than in `AuditExplorer` because the lint says
     * it must.** *"Only the impersonation service opens, reads or closes a
     * support session"* is claimed in the model's docblock, in the migration and
     * on the scope allowlist, and `ArchitectureTest` enforces it — so the
     * explorer adding `ImpersonationSession::query()` of its own would have been
     * a weakening of that lint disguised as a new screen. Reading is named in
     * the claim, not only writing, and the reason is on the test: nothing
     * beneath the application layer refuses a stray read of *who support has
     * been visiting*, because the RLS policy admits everything by construction
     * (562). The chokepoint is the whole protection, so the reader comes to it.
     *
     * A null `$agentId` is every agent, which is the incident question — "who
     * has been in accounts today" — rather than a missing filter.
     *
     * @return LengthAwarePaginator<int, ImpersonationSession>
     */
    public function history(?int $agentId, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        // ⚠️ `agent` ONLY. Eager-loading `business` throws `TenantNotResolved`,
        // and finding that out is what decided the shape of the screen above
        // this: `Business` carries the tenant scope, the caller is a platform
        // admin with no tenant, and every row on this page names a *different*
        // business. Resolving them all would be `Tenancy::actingAs()` in a loop
        // — which is decision 569's account list assembled through a side door,
        // arrived at as a side effect of wanting a nicer table cell. The screen
        // shows the account number and the operator opens the one they want.
        // `User` is not tenant-owned, so the agent's own name is free.
        $query = ImpersonationSession::query()->with('agent');

        if ($agentId !== null) {
            $query->where('agent_id', $agentId);
        }

        // `id`, not `started_at`. Postgres sorts NULL first on a DESC order by
        // (289), and `id` is the one column the lint permits here — they agree,
        // because nothing backdates a session and the two orders cannot differ.
        return $query->orderByDesc('id')->paginate($perPage, pageName: $pageName);
    }

    /**
     * The live session for this request, or null.
     *
     * Never trusts the pointer. A row that has ended or lapsed is closed here
     * and reported as absent, so a caller cannot distinguish "expired" from
     * "never was" and therefore cannot accidentally honour the first.
     */
    public function current(): ?ImpersonationSession
    {
        $id = $this->currentSessionId();

        if ($id === null) {
            return null;
        }

        $session = ImpersonationSession::query()->find($id);

        if ($session === null) {
            $this->forgetPointer();

            return null;
        }

        if ($session->ended_at !== null) {
            $this->forgetPointer();

            return null;
        }

        if (! $session->expires_at->isFuture()) {
            $this->close($session, ImpersonationEnding::Expired);

            return null;
        }

        return $session;
    }

    /**
     * End the session this request is in, and hand the agent back their own login.
     *
     * Safe to call when there is nothing to end — the agent-logout path calls
     * it unconditionally, and a logout that throws because there was no session
     * is a worse failure than the one it was guarding against.
     */
    public function stop(ImpersonationEnding $ending = ImpersonationEnding::Ended): void
    {
        $id = $this->currentSessionId();
        $agentId = $this->session->get(self::AGENT_KEY);

        if (is_int($id)) {
            $session = ImpersonationSession::query()->find($id);

            if ($session instanceof ImpersonationSession && $session->ended_at === null) {
                $this->close($session, $ending);
            }
        }

        $this->forgetPointer();

        // Give the agent their own account back rather than logging them out.
        // Being dumped at the login screen after every support session is the
        // kind of friction that gets a tool used less carefully, not less.
        if (is_int($agentId) && $ending !== ImpersonationEnding::AgentLogout) {
            $agent = User::query()->find($agentId);

            if ($agent instanceof User) {
                // ⚠️ Not a sign-in, and `staff_events` must not say it was.
                // `28` §9.1 records every internal login; handing an agent back
                // the account they never left is the end of a support session,
                // and a row claiming otherwise would appear on the staff view
                // once per session, indistinguishable from the real thing.
                StaffDirectory::withoutSignInRecord(fn () => Auth::login($agent));
                $this->session->migrate(destroy: false);

                return;
            }
        }

        // No agent to return to. Logging out is the only safe end — the
        // alternative leaves the request authenticated as the owner with
        // nothing recording why.
        Auth::logout();
    }

    /**
     * Refuse a capability `28` §9.4 puts out of reach, even in act-as mode.
     *
     * Called by the service that owns the capability, not by a route: Livewire
     * routes every interaction through one endpoint, so there is no route-shaped
     * thing to guard. Inert when no session is open, which is the ordinary case
     * for every caller — the owner doing it themselves is always allowed.
     *
     * ## ⚠️ THE ATTEMPT IS RECORDED, AND IT WAS NOT (1996)
     *
     * `28` §9.4 logs every impersonated page view and every act-as write, and an
     * agent *attempting* the single most sensitive action on the blocklist left
     * no trace at all — both export halves threw or 403'd and wrote nothing.
     * The page-view counter says an agent loaded a URL; it does not say they
     * reached for a capability two people are supposed to authorise, which is
     * the fact an incident review is looking for.
     *
     * It is recorded **here** rather than at each call site so that the next
     * blocklisted capability gets it without anybody remembering to — 1904 and
     * 1905 added two guards and neither carried a record.
     *
     * ⚠️ **AGAINST THE SESSION'S OWN TENANT, NEVER THE AMBIENT ONE.**
     * {@see ExportBuilder::request()} calls this *before* its
     * `Tenancy::actingAs()`, and the ops path has no tenant at all — so the
     * session's `business_id` is the only reliable answer, and it is also the
     * right one: an auditor asking *"who reached for what inside this account"*
     * reads the tenant's own log.
     *
     * ⚠️ **AND THE WRITE HAS TO CLIMB OVER THE READ-ONLY CONNECTION**, because
     * a **view-only** session is the likeliest place this fires and its
     * connection refuses every INSERT. See
     * {@see ReadOnlyConnection::forOurOwnRecord()} — including why no test in
     * this repository can catch getting that wrong.
     *
     * @throws ImpersonationRefused
     */
    public function refuse(ImpersonationCapability $capability): void
    {
        $session = $this->current();

        if ($session === null) {
            return;
        }

        ReadOnlyConnection::forOurOwnRecord(function () use ($session, $capability): void {
            Tenancy::actingAs($session->business_id, function () use ($session, $capability): void {
                $this->audit->record(
                    action: 'impersonation.refused',
                    actor: $session->auditActor(),
                    metadata: [
                        'capability' => $capability->value,
                        'mode' => $session->mode->value,
                        'impersonation_session_id' => $session->id,
                        'ticket_ref' => $session->ticket_ref,
                    ],
                );
            });
        });

        throw ImpersonationRefused::capability($capability);
    }

    /**
     * Count a page an agent looked at (`28` §9.4, view-only included).
     *
     * A counter rather than a row per view: the detail belongs in `audit_log`,
     * and a row per page view in `impersonation_sessions` would bury the
     * sessions among their own traffic.
     */
    public function recordPageView(ImpersonationSession $session): void
    {
        DB::table('impersonation_sessions')
            ->where('id', $session->id)
            ->increment('page_views');
    }

    /**
     * Record a change made on the owner's behalf — audit log *and* their own feed.
     *
     * `28` §9.4's attribution rule, and the pairing is the rule rather than
     * belt-and-braces: the audit entry is what an auditor reads, the feed entry
     * is what the owner sees, and dropping either one leaves a change that
     * somebody cannot find out about.
     *
     * ⛔ **THE SUBJECT IS AN ENUM AND USED TO BE TWO FREE STRINGS** (6649,
     * closed 6800). This was the last free-text source of an `activity_feed`
     * title on the platform — a field `Livewire\Account\Activity` prints
     * **verbatim**, on an **append-only** table — and what kept a customer's
     * name out of it was that nothing called this method. A habit is not a
     * control. {@see SupportWriteSubject} is the closed vocabulary, and it
     * carries the audit action too, so the auditor's record and the owner's
     * sentence are one fact rather than two that can disagree.
     *
     * ⚠️ **NOTHING HERE VALIDATES A STRING, BECAUSE THERE IS NO STRING TO
     * VALIDATE** — 6643's allowlist-never-a-scrubber ruling, taken to the one
     * call shape where typing the parameter costs nothing (6645).
     *
     * ⚠️ **`$metadata` IS STILL FREE-FORM AND STILL GOES ONLY TO THE AUDIT
     * LOG**, which is where a support write's detail belongs and which is
     * rendered to nobody in a hurry. Its rule against customer content is the
     * one it has always had.
     *
     * ⛔ **THIS METHOD HAD ZERO CALLERS, AND THAT IS WHY THE PARAGRAPH ABOVE
     * READS IN THE PAST TENSE — CORRECTED WAVE 38 (10630).** *"What kept a
     * customer's name out of it was that nothing called this method"* was
     * true of the vocabulary's own hazard and left a second one standing:
     * `impersonation_sessions.writes` was permanently 0, rendered to staff as
     * fact by `StaffActivity`, and `SupportSessionSummary` emailed every
     * owner a count of 0 against an empty feed on every act-as session that
     * changed something. **All four {@see SupportWriteSubject} writers call
     * this now** — see that enum's own docblock for the census.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordWrite(
        ImpersonationSession $session,
        SupportWriteSubject $subject,
        array $metadata = [],
    ): void {
        DB::table('impersonation_sessions')
            ->where('id', $session->id)
            ->increment('writes');

        $ticket = $session->ticket_ref;

        Tenancy::actingAs($session->business_id, function () use ($session, $subject, $metadata, $ticket): void {
            $this->audit->record(
                action: $subject->auditAction(),
                actor: $session->auditActor(),
                metadata: $metadata + [
                    'impersonation_session_id' => $session->id,
                    'ticket_ref' => $ticket,
                ],
            );

            $this->activity->record(
                action: AutopilotActionType::SupportMadeAChange,
                // `28` §9.4's sentence: "GO AI EZ support updated {thing} for
                // you ({ticket ref})". The thing comes off the enum; the ticket
                // reference is the session's own and was never the caller's.
                title: $ticket === null
                    ? 'GO AI EZ support '.$subject->describedToOwner().' for you'
                    : 'GO AI EZ support '.$subject->describedToOwner().' for you ('.$ticket.')',
                // ⚠️ No metadata. ActivityRecorded::broadcastWith() is an
                // allowlist that excludes it (decision 384), so nothing here
                // would reach a socket — but the reason to leave it empty is
                // that a support write's detail belongs in the audit entry
                // above, where it is not rendered to anybody in a hurry.
            );
        });
    }

    /**
     * @throws ImpersonationRefused
     */
    private function refuseIfRoleCannot(User $agent, ImpersonationMode $mode): void
    {
        $strongest = $agent->role->strongestImpersonationMode();

        if ($strongest === null) {
            throw ImpersonationRefused::because(
                'Your role cannot open a support session.'
            );
        }

        if ($mode === ImpersonationMode::Act && $strongest !== ImpersonationMode::Act) {
            throw ImpersonationRefused::because(
                'Making changes inside an account needs a support lead. You can open it read-only.'
            );
        }
    }

    /**
     * @throws ImpersonationRefused
     */
    private function refuseIfReasonIsThin(string $reason): void
    {
        if (mb_strlen($reason) < self::MINIMUM_REASON) {
            throw ImpersonationRefused::because(
                'Say why you need to go in — one sentence. It is recorded against the account.'
            );
        }
    }

    /**
     * @throws ImpersonationRefused
     */
    private function refuseIfTicketMissing(ImpersonationMode $mode, ?string $ticketRef): void
    {
        if ($mode === ImpersonationMode::Act && ($ticketRef === null || trim($ticketRef) === '')) {
            throw ImpersonationRefused::because(
                'Making changes needs a ticket reference so the owner can see what this was about.'
            );
        }
    }

    /**
     * The seam PHP isolation will fill.
     *
     * ⚠️ **Today this refuses nothing, and that is stated rather than hidden.**
     * `28` §9.4 requires two things this application cannot yet answer: message
     * bodies masked for PHI-classified tenants unless the agent holds
     * `phi_access`, and offshore roles unable to impersonate a PHI tenant at
     * all. Both need the classification and the staff flag that PHI isolation
     * owns, and neither is guessable from here — an `offshore` column does not
     * exist, and inventing one would put a security control on a field nobody
     * sets.
     *
     * It is a method rather than a comment because the difference between "we
     * thought about this" and "there is one place to put it" is the difference
     * between a seam and a gap.
     */
    private function refuseIfUnreachable(Business $business): void
    {
        // Intentionally empty. See the docblock.
    }

    private function close(ImpersonationSession $session, ImpersonationEnding $ending): void
    {
        $session->forceFill([
            'ended_at' => now(),
            'ended_reason' => $ending,
        ])->save();

        Tenancy::actingAs($session->business_id, function () use ($session, $ending): void {
            $this->audit->record(
                action: 'impersonation.ended',
                actor: $session->auditActor(),
                metadata: [
                    'ended_reason' => $ending->value,
                    'page_views' => $session->page_views,
                    'writes' => $session->writes,
                    'mode' => $session->mode->value,
                ],
            );
        });

        $this->notifyOwner($session);

        $this->forgetPointer();
    }

    /**
     * `28` §9.4's owner notification — the seam decision 571 left open.
     *
     * ⚠️ **AFTER THE AUDIT ROW, NEVER BEFORE.** The record of the session ending
     * is the thing that must exist; the email is a courtesy on top of it. Sending
     * first and then failing to record would leave an owner holding a message
     * about a session the log does not show ending, which is the shape decision
     * 379 fixed for broadcasts.
     *
     * ⚠️ **IT CANNOT FAIL A CLOSE, BY CONSTRUCTION RATHER THAN BY TRY/CATCH.**
     * `PlatformMailer::send()` queues and does not touch the transport, so there
     * is nothing here to catch. That matters because `close()` also runs from the
     * `Impersonating` middleware when a session expires mid-request: an
     * expired session has to end on the next request whatever else is broken,
     * and that is `28` §9.5's fourth build-failing test.
     */
    private function notifyOwner(ImpersonationSession $session): void
    {
        // Act-as only — see the class docblock for why view-only is silent.
        if ($session->mode !== ImpersonationMode::Act) {
            return;
        }

        // ⚠️ **`=== false`, not `!== true`, and the inversion is deliberate.**
        // `ZernioGbpClient::assertEnabled()` reads its flag the other way round,
        // and both are fail-closed — they just close in opposite directions,
        // because the risk is not symmetrical. A malformed value on a flag that
        // authorises third-party spend must mean *do not spend*. A malformed
        // value on a flag that authorises **telling a customer we were inside
        // their account** must mean *tell them*: the failure mode of a stray
        // send is a redundant email, and the failure mode of a stray silence is
        // an undisclosed support session, which is the thing `28` §9.4 exists to
        // prevent. Only somebody deliberately writing `false` turns it off.
        if ($this->defaults->value('impersonation.notify_owner') === false) {
            return;
        }

        // ⚠️ Inside the tenancy, not around a `withoutGlobalScopes()`. `Business`
        // is RLS-`FORCE`d on its own id, so dropping the application scope would
        // leave the database returning nothing beneath it — decision 625's trap,
        // where the one-line fix reads as broken rather than as refused. This is
        // 569's by-reference pattern: the session names its tenant, so there is
        // one to act as.
        $owner = Tenancy::actingAs($session->business_id, function () use ($session): ?User {
            $business = Business::query()->find($session->business_id);

            return $business instanceof Business
                ? User::query()->find($business->owner_user_id)
                : null;
        });

        // `start()` refuses a business with no owner, so reaching here means the
        // row was torn apart while the session was open. There is nobody to tell
        // and nothing to do about it; the audit entry above is already written.
        if (! $owner instanceof User || trim((string) $owner->email) === '') {
            return;
        }

        $agent = User::query()->find($session->agent_id);

        $this->mailer->send($owner->email, new SupportSessionSummary(
            agentName: $agent instanceof User ? $agent->name : 'A member of our team',
            reason: $session->reason,
            ticketRef: $session->ticket_ref,
            changes: $session->writes,
        ));
    }

    private function currentSessionId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_int($id) ? $id : null;
    }

    private function forgetPointer(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->forget(self::AGENT_KEY);
    }
}
