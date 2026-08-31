<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ops\PlatformHealthChecks;
use App\Support\TableHorizons;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;

/**
 * Delete failed-job records past {@see self::RETENTION_DAYS} — decisions
 * 8610-8639.
 *
 * ## ⛔ WHAT THIS TABLE ACTUALLY HOLDS, AND WHY IT IS NOT HOUSEKEEPING
 *
 * A `failed_jobs` row is the **serialised job**, which means it is whatever the
 * job's constructor was handed. On this platform that is, routinely and by
 * design, another person's personal data in cleartext:
 *
 *   - `DeliverPlatformMail` takes the recipient's email address as a promoted
 *     constructor property, because it cannot send mail without it — and takes
 *     the whole notification object beside it, so that object's own constructor
 *     arguments ride nested inside this one.
 *   - `MagicLinkLogin` is the notification that makes the sentence above
 *     concrete: its payload is a **sign-in URL with the token in it**. It
 *     implements the queue contract itself, so it also rides on its own.
 *   - `PasswordResetLink` is the same sentence with a longer clock: a reset
 *     URL with the live token in it, expiring in `config/auth.php`'s
 *     `passwords.users.expire` minutes rather than fifteen. It reaches this
 *     table at all only because 9600 routed the reset email through
 *     `PlatformMailer`, which is what queues it.
 *   - `SendMissedCallTextBackJob` carries a whole `InboundCall`, and therefore
 *     a caller's mobile number.
 *   - `ArchivePixelBatchJob` carries a site visitor's raw event batch and the
 *     hash of their IP. It is the best-argued entry on this list — a `Phi`
 *     business and every value-shaped field are refused *before* dispatch, so
 *     nothing reaches the queue that may not reach L0 — and it is still an
 *     entry.
 *   - `CaptureInboundMediaJob` carries the carrier's URL to a picture a member
 *     of the public sent to a business.
 *
 * ⛔ **THAT LIST USED TO BE A DIFFERENT SIX AND IT READ AS A CENSUS — CORRECTED
 * 2026-08-23 (8725-8735).** It named *"`TextBackMissedCaller` and
 * `MissedCallTextBack` carry a whole `InboundCall`"*, and **neither is a queued
 * class**: the first is a synchronous listener that says so in its own docblock
 * and the second is a service. What puts that `InboundCall` on a payload is
 * `SendMissedCallTextBackJob`, which the list did not name. It also named
 * `EscalateUrgentThreadJob` and `IngestVoiceEventJob` as saying *"the same
 * thing about their own payloads"* — true as a pointer to two good comments,
 * and both of those payloads carry a tenant's own configured words and an
 * opaque vendor call handle rather than anybody's personal data. And it missed
 * three of the five above.
 *
 * ⛔ **THE FIX IS NOT A LONGER LIST.** This one is derived and checked:
 * `Architecture/QueuePayloadTest` reflects over every class in `app/` that
 * implements the queue contract, enumerates every constructor parameter, and
 * fails the build on a job or a parameter that has not been classified — and
 * then asserts that **every class it classifies as carrying another person's
 * data is named in this file**. So the two cannot drift, and the thing that
 * decayed here was the only part of it a machine could not have written: the
 * judgement about which `string` is a person. That test states in full what it
 * cannot see, which is the other half of not pretending to be a census.
 *
 * ⛔ **THOSE CLASS NAMES ARE BARE, NEVER NAMESPACED AND NEVER IN AN `@see`,
 * AND THAT IS NOT A STYLE CHOICE — DO NOT TIDY IT.** `composer lint`'s
 * `fully_qualified_strict_types` fixer hoists **any** namespaced name out of a
 * docblock into a real `use` statement, backticks and all — and a `use` is
 * **code**. `OutboundTest` and `EmailMeteringTest` both hold `PlatformMailer` as
 * the only file in `app/` allowed to name the mail job, scanning source through
 * `codeWithoutComments()`. The first draft of this docblock cited it properly,
 * Pint turned the citation into an import, and both chokepoint lints reddened.
 * **They were right and the docblock was wrong** — decision 8625, recorded
 * because the tempting fix is to exempt this file in the lint, which would open
 * a real chokepoint in order to accommodate a formatter.
 *
 * ⛔ **AND THE TABLE HAS NO OTHER BOUND OF ANY KIND.** It is one of 3148's
 * thirty-six tables without row-level security, so no global scope and no tenant
 * predicate reaches it; erasure cannot reach it either, because erasure here is
 * delete-plus-survivors over rows (1380) and a queue payload is not a row anybody
 * can name. Before this command the only thing that removed a `failed_jobs` row
 * was an operator typing `queue:flush`.
 *
 * ## ⛔ THE PERIOD IS THE DECISION AND IT IS A TRADE, NOT A DEFAULT
 *
 * **Pruning this table destroys the evidence an operator diagnoses with**, and
 * that is not incidental — it is a trade this codebase has already made, out
 * loud, in the opposite direction. `DeliverPlatformMail` deliberately keeps the
 * recipient's address **out of the log line** on the stated grounds that
 * *"`failed_jobs` already holds the payload for whoever is actually diagnosing
 * it"*. Nine files in `app/` reason from that payload being there. A horizon
 * short enough to be privacy-optimal would silently invalidate all nine.
 *
 * So: **thirty days keeps every one of those nine trades intact, and ends
 * "for ever."** What is given up is the ability to replay or diagnose a failure
 * older than a month — an operator who was away for five weeks loses the
 * payload. What is bought is that no end-customer's mobile number, email address
 * or sign-in token accumulates in an unprotected table for a second month.
 *
 * ⚠️ **AND IT COSTS THE ONE READER NOTHING.**
 * `PlatformHealthChecks::checkFailedJobs()` is this table's only reader in the
 * whole application, and it counts `ops.health_window_minutes` — sixty — so no
 * horizon above a day can reach it. The failed-job spike alert sees exactly
 * what it saw before.
 *
 * ## ⚠️ WHICH PRECEDENT GOVERNS, BECAUSE TWO PULL OPPOSITE WAYS
 *
 * `storage.retention_days.{kind}` is a registry row with **no seed**, because
 * *"a retention PERIOD is the owner's ruling and this codebase may not guess
 * one"* (4941-4942). That precedent does **not** govern here, and the reason is
 * the direction its failure points. Those four kinds are things a customer
 * handed us on purpose — a photograph, a voicemail, an uploaded document — held
 * as the product, on a screen, where an unset period prunes nothing and the
 * conservative answer is to keep. **A `failed_jobs` row is an accident.** Nobody
 * chose to store it, no screen renders it, no export includes it and no
 * published term names it, so there is no promise to a person for an owner to
 * make. Making this an unset Ops row would mean the table goes on being
 * unbounded until somebody types a number, which is precisely the state this
 * command exists to end: **fail-closed here means deleting, not keeping.**
 *
 * ✅ **What governs is `magic_link_tokens` — an operational constant, chosen in
 * code, stated where the operator reads it** — and the owner named it as the
 * precedent when ruling on 2026-08-23. `PruneTrialOriginClaims` made the same
 * move for the same reason: 502's withhold-rather-than-default posture is about
 * **prices the owner owns**, and `CLAUDE.md`'s first tiebreaker — *less stored
 * PII* — points at a number rather than at indefinite.
 *
 * ## ⛔ THIS IS A HORIZON AND IT IS NOT AN ERASURE PATH
 *
 * A data subject who exercises erasure today still has anything of theirs that
 * is sitting in a `failed_jobs` payload survive the request. This command does
 * not change that. **What it changes is how long the residue lives** — from
 * indefinitely to at most thirty days from the failure, with no way to shorten
 * it for one person. Saying otherwise would be 314-316 inside the slice that
 * narrows it. See decision 8615.
 *
 * ## ⚠️ WHY IT WRAPS THE FRAMEWORK'S SWEEP RATHER THAN WRITING ITS OWN DELETE
 *
 * `failed_jobs` is the framework's table, its shape is `config/queue.php`'s
 * `failed` block, and `PrunableFailedJobProvider::prune()` is already chunked at
 * a thousand rows a statement. A hand-written `DELETE` here would be a second
 * implementation of somebody else's schema that a driver change would silently
 * orphan.
 *
 * ⛔ **AND IT IS NOT `queue:prune-failed` ON THE SCHEDULE, WHICH WAS THE FIRST
 * DESIGN.** Three reasons, and the first is the binding one:
 * `TableHorizons` states every period as a **constant** and never as a number
 * typed into the list, because *"a literal 90 would be right
 * on the day it was typed and silent on the day the constant moved."* The
 * framework command takes its period as `--hours=`, so scheduling it would put
 * the number in `routes/console.php` and the words in `TableHorizons` with
 * nothing holding them together. Second, its `--hours` **defaults to 24**, so a
 * schedule line that lost its option would quietly prune at a day and read as
 * correct. Third, this file is where the trade above has to live: nine comments
 * in `app/` reason from this table's contents, and the answer to them needs an
 * address.
 */
#[Signature('jobs:prune-failed')]
#[Description('Delete failed-job records past their retention window')]
final class PruneFailedJobs extends Command
{
    /**
     * How long a failed-job record is kept.
     *
     * ⚠️ **NOT A REGISTRY KEY, ON {@see WatchPlatformHealth::KEEP_DAYS}' AND
     * {@see PrunePlatformMailSends::RETENTION_DAYS}' ARGUMENT.** An Ops box here
     * would offer an operator exactly one capability — keeping other people's
     * phone numbers longer — and the number is an engineering rail rather than a
     * promise anybody has been made.
     *
     * ⛔ **THIRTY RATHER THAN SEVEN, AND THAT IS THE HALF WORTH ARGUING.** The
     * *less stored PII* tiebreaker is `CLAUDE.md`'s first and it points shorter;
     * what stops it at thirty is that the diagnostic value of this table is not
     * a nicety but a trade already made in nine files, and a week does not
     * survive one person being away. The marginal privacy gain from thirty to
     * seven is small beside the gain from *for ever* to thirty; the marginal
     * diagnostic loss is not.
     *
     * ⛔ **AND THIRTY RATHER THAN NINETY OR A HUNDRED AND EIGHTY**, which are
     * this schema's other two pruner figures. `public_audits` keeps ninety
     * because its subject is a visitor who never signed up; `trial_claims` keeps
     * a hundred and eighty because its rows are a live account's fraud signal
     * and being wrong costs a real customer their allowance. Neither reasoning
     * transfers: nothing here is a signal anybody reads later, and the row is the
     * least protected copy of end-customer contact details in the schema. Thirty
     * is `platform_mail_sends`, `platform_health_windows` and
     * `sending_health_windows` — the platform's own-machinery figure — and this
     * is the platform's own machinery with somebody else's data caught in it.
     */
    public const int RETENTION_DAYS = 30;

    /**
     * ⚠️ **THE CONTRACT RATHER THAN THE `queue.failer` STRING, AND IT IS THE
     * DIFFERENCE BETWEEN A REAL NARROWING AND A DECORATIVE ONE.** Resolving the
     * container key hands static analysis the concrete provider this
     * deployment's `config/queue.php` happens to select, so the guard below
     * reads as *always true* and would have to be argued away. The alias
     * `FailedJobProviderInterface::class => 'queue.failer'` is the framework's
     * own, the binding is the same singleton, and asking for the interface is
     * what makes the check about the driver an operator can change rather than
     * about the one that is set today.
     */
    public function handle(FailedJobProviderInterface $failer): int
    {
        // ⛔ **A DRIVER THAT CANNOT PRUNE IS REFUSED RATHER THAN REPORTED AS
        // ZERO.** `queue.failed.driver` is an env value, and `null` and any
        // future driver that stores without pruning would leave this command
        // printing a cheerful figure while the horizon `TableHorizons` claims
        // for this table bounded nothing — which is exactly the shape
        // `SendingHealth::prune()` held for eleven months (7785(d)). "Deleted
        // nothing" is also what a healthy night looks like, so a pruner is the
        // worst possible host for a silent no-op.
        if (! $failer instanceof PrunableFailedJobProvider) {
            $this->error(
                'The configured failed-job driver ('.(string) config('queue.failed.driver')
                .') cannot prune, so failed_jobs has no retention horizon on this '
                .'deployment. App\Support\TableHorizons says it has one.'
            );

            return self::FAILURE;
        }

        $deleted = $failer->prune(CarbonImmutable::now()->subDays(self::RETENTION_DAYS));

        $this->info($deleted === 0
            ? 'No failed-job records older than '.self::RETENTION_DAYS.' days to prune.'
            : "Pruned {$deleted} failed-job ".str('record')->plural($deleted)
                .' older than '.self::RETENTION_DAYS.' days.');

        return self::SUCCESS;
    }
}
