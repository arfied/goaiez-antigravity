<?php

declare(strict_types=1);

namespace App\Services;

use App\Console\Commands\PruneMagicLinkTokens;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkLogin;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Support\MagicLinkRateLimits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Passwordless sign-in by email — the fallback that must keep working.
 *
 * CLAUDE.md is explicit about why this exists alongside passkeys rather than
 * being replaced by them: `laravel/passkeys` is v0.2.1, pre-1.0, and on the
 * login path. If it breaks, this is how people get in. It is therefore not a
 * convenience feature and does not get to be the shakier of the two.
 *
 * THREE PROPERTIES, each of which is the difference between this and a signed
 * URL:
 *
 *   single use    the row is marked consumed inside the same conditional update
 *                 that claims it, so two simultaneous clicks cannot both win.
 *                 A signed URL is replayable for its whole lifetime, and login
 *                 links get forwarded, quoted in replies, and followed by mail
 *                 scanners that visit every URL they see.
 *
 *   stored hashed the database holds SHA-256 of the token. A dump must not be a
 *                 set of live logins.
 *
 *   silent        request() behaves identically whether or not the address has
 *                 an account, and the send is queued so the response time does
 *                 not answer the question either. ⚠️ THERE ARE NOW THREE ARMS
 *                 RATHER THAN TWO — unknown address, cooled address, sent — and
 *                 all three return void with nothing flashed, which is what the
 *                 cooldown added at 9721 had to preserve.
 */
final class MagicLinkService
{
    public function __construct(
        private readonly PlatformMailer $mailer,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * Minutes a link stays usable.
     *
     * Fifteen rather than sixty: long enough to switch to a phone and find the
     * email, short enough that a forwarded thread goes stale before anyone reads
     * it.
     */
    public const int LIFETIME_MINUTES = 15;

    public function lifetimeMinutes(): int
    {
        return $this->defaults->int('auth.magic_link.lifetime_minutes');
    }

    /**
     * Issue a link, if the address belongs to someone.
     *
     * Returns nothing in every case. A caller cannot distinguish "sent" from
     * "no such account", which is the point — an endpoint that does becomes an
     * account enumeration oracle, and this one is unauthenticated by definition.
     */
    public function request(string $email, ?string $ipHash = null): void
    {
        $email = Str::lower(trim($email));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        // ⛔ THE PER-RECIPIENT COOLDOWN, AND THE HEADLINE ABOUT THIS DOOR IS
        // THAT THE SIBLING DOOR HAD ONE AND THIS ONE DID NOT (9721).
        // `config/auth.php` sets `passwords.users.throttle` to 60 and
        // `DatabaseTokenRepository::create()` refuses a second reset token for
        // the same user inside that window WHATEVER ADDRESS ASKED — which is
        // why 9621 had to handle a `RESET_THROTTLED` arm at all. Nothing here
        // did the equivalent: every call minted a fresh token, so a victim
        // received as many messages an hour as an attacker had addresses, and
        // the route throttle — per network, by construction — could not bound
        // that at any figure.
        //
        // ⚠️ AND IT IS SILENT, WHICH IS NOT A CONVENIENCE. This method's
        // contract is that no caller can tell any arm from any other (see the
        // class docblock), so a cooled address returns exactly as an address
        // with no account does. ⚠️ It also NARROWS 9504's timing channel rather
        // than widening it: the arm that skips is the arm that would otherwise
        // pay a transport round trip on `QUEUE_CONNECTION=sync`.
        //
        // ⚠️ BEFORE THE TOKEN IS MINTED AND NOT AFTER. A row written and then
        // not mailed is a link nobody can use occupying the cooldown that stops
        // the next real one — which would turn a flood into a lockout of the
        // person being flooded.
        if (MagicLinkRateLimits::recentlyIssuedTo($email)) {
            return;
        }

        // 32 bytes of CSPRNG output. Long enough that guessing is not a threat
        // model, which is also why the stored hash is SHA-256 rather than bcrypt
        // — there is no dictionary to slow an attacker down through, and the
        // lookup has to be an index seek on the hash, which bcrypt's per-row
        // salt makes impossible.
        $token = Str::random(64);

        MagicLinkToken::create([
            'email' => $email,
            'token_hash' => self::hash($token),
            'expires_at' => Carbon::now()->addMinutes($this->lifetimeMinutes()),
            'requested_ip_hash' => $ipHash,
        ]);

        // An address, not a user: the link is addressed to the mailbox that
        // asked, and routing it through the user model would send it to whatever
        // address the record happens to hold. Those are the same value here —
        // the lookup was by email — and keeping them the same value on purpose
        // is what stops a future "notify by user id" refactor mailing a link
        // somewhere the requester does not control.
        //
        // ⚠️ `PlatformMailer::send()` queues and cannot throw. That is not
        // incidental here: this method's silence is a security property (see the
        // class docblock), and a synchronous mailer refusal would break it by
        // failing loudly for exactly the addresses that have an account.
        //
        // ⛔ **AND THAT SENTENCE WAS TRUE OF ONE EXCEPTION TYPE AND WRITTEN AS
        // THOUGH IT WERE TRUE OF ALL OF THEM, FOR A YEAR — CORRECTED 2026-08-25
        // (9500).** It used to end *"the reasoning is on `DeliverPlatformMail`,
        // where the guard actually runs"*, and that job's `catch` names
        // `MailNotDeliverable` and nothing else. `SyncQueue::handleException()`
        // calls `$job->fail($e)` and then **rethrows**, so on
        // `QUEUE_CONNECTION=sync` a Symfony `TransportException` — a wrong SMTP
        // password, which is what three production `failed_jobs` rows carried on
        // 2026-08-20 — came back out of `dispatch()` and landed **here**, on the
        // line below, in the one method whose whole contract is that it cannot
        // be told apart from a no-op. The login page answered **500 for an
        // address with an account and 302 for one without.**
        //
        // ⚠️ **THE OLD SENTENCE IS KEPT BECAUSE IT IS THE EVIDENCE**: it is
        // 314–316 on the login path, written in the file whose class docblock
        // calls the silence a security property, and nothing in this repository
        // could have contradicted it — the three tests that asserted the
        // equality all ran under `Notification::fake()`, which can never produce
        // a transport exception at all.
        //
        // ✅ **What makes it true now is a `catch` in `send()` rather than this
        // comment.** The containment is at the call site because that is the
        // only place that can have it *and* keep the job's retry ladder, and it
        // is driven at this route by `LoginMethodsTest`'s *"a transport that
        // refuses never reveals whether an account exists"*.
        //
        // ⛔ **WHAT IS STILL OPEN, SO NOBODY READS THIS PARAGRAPH AS THE WHOLE
        // ANSWER** (9504): on `sync` the send happens **inside this request**,
        // so the address that has an account pays a transport round trip and the
        // one that does not returns immediately. That is the same question
        // answered with a stopwatch instead of a status code, it is a property
        // of the queue driver rather than of this code, and `MagicLinkController`
        // now says so where it used to claim the opposite.
        $this->mailer->send(
            $email,
            new MagicLinkLogin(
                route('magic-link.consume', ['token' => $token]),
                $this->lifetimeMinutes(),
            ),
        );
    }

    /**
     * Spend a token, returning the user it belongs to.
     *
     * Null for anything that is not a live, unspent token: unknown, expired,
     * already used, or belonging to an account that has since been deleted. The
     * caller must not be able to tell which — every one of them is "that link
     * did not work".
     */
    public function consume(string $token): ?User
    {
        $hash = self::hash($token);

        // A conditional UPDATE, not a read-then-write. Two clicks arriving
        // together — a mail scanner prefetching while the person clicks — would
        // both pass a `where consumed_at is null` SELECT and both proceed. The
        // database decides instead, and exactly one row is affected.
        $claimed = MagicLinkToken::query()
            ->where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now())
            ->update(['consumed_at' => Carbon::now()]);

        if ($claimed !== 1) {
            return null;
        }

        $record = MagicLinkToken::query()->where('token_hash', $hash)->first();

        if ($record === null) {
            return null;
        }

        return User::query()->where('email', $record->email)->first();
    }

    /**
     * Rows per DELETE.
     *
     * `PrunePublicAudits::CHUNK`'s figure and its reasoning: large enough that
     * an ordinary night is one statement, small enough that no single statement
     * holds locks for long on a table the **login path** writes to.
     *
     * ⚠️ **THE RUN THIS IS FOR IS THE FIRST ONE.** Ordinary nightly volume here
     * is one row per sign-in request and would not need chunking at all; the
     * sweep's first execution deletes every row written since 2026-07-30,
     * unattended, at 03:25. A pruner arriving after a table has been growing
     * unbounded has exactly one enormous run, and it is the run nobody watches.
     */
    private const int PRUNE_CHUNK = 500;

    /**
     * Delete tokens that can no longer be used.
     *
     * Consumed rows are kept for a while rather than deleted on use, so a replay
     * is distinguishable from an expiry when someone asks why a link failed.
     * A day is long enough for that conversation.
     *
     * ⛔ **"A WHILE" WAS FOR EVER UNTIL 2026-08-22, BECAUSE THIS METHOD HAD NO
     * CALLER — NOT IN `app/`, NOT IN `routes/console.php`, NOT IN A TEST**
     * (7885). Both the paragraph above and the creating migration's *"pruning is
     * one scheduled sweep rather than a delete on the login path"* described a
     * sweep that did not exist, so `magic_link_tokens` was a permanent record of
     * **when each account holder asked to sign in and from which network**, back
     * to the day the table shipped. {@see PruneMagicLinkTokens} is the caller.
     *
     * ⚠️ **THE PERIOD IS UNCHANGED AND IS DELIBERATELY NOT A REGISTRY ROW**
     * (7886) — see that command for why this is not `storage.retention_days.*`
     * (4941/4942).
     *
     * ⚠️ **NO TENANT IS ESTABLISHED AND NONE IS NEEDED.** `magic_link_tokens`
     * carries no `business_id`, is named in `TenancyTest`'s `$exempt` census
     * (*"issued to an email, read before auth"*), and `pg_class` reports
     * `relrowsecurity` and `relforcerowsecurity` **false** with no policy — read
     * on 2026-08-22 rather than assumed. That is what makes a plain range DELETE
     * from a console command match rows here where `IngestRejects::prune()`'s
     * would match none (7626, 7785(d)).
     *
     * @return int rows deleted
     */
    public function prune(): int
    {
        // Hoisted, so the boundary cannot drift forward under a long sweep and
        // leave a tail of rows that were eligible when the first statement ran.
        $cut = Carbon::now()->subDay();

        $deleted = 0;

        do {
            $batch = MagicLinkToken::query()
                ->where('expires_at', '<', $cut)
                ->limit(self::PRUNE_CHUNK)
                ->delete();

            $deleted += $batch;
        } while ($batch === self::PRUNE_CHUNK);

        return $deleted;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
