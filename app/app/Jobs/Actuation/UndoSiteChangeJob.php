<?php

declare(strict_types=1);

namespace App\Jobs\Actuation;

use App\Enums\AutopilotActionType;
use App\Enums\SiteChangeActor;
use App\Enums\SiteChangeUndoState;
use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Services\ActivityService;
use App\Services\Actuation\ActuationActor;
use App\Services\Actuation\SiteChanges;
use App\Support\OutboundSiteBudget;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The owner pressed Undo, and undoing means talking to their website — so it
 * happens here rather than in the request they are waiting on.
 *
 * `BUILD-PLAN` §2.11.3 slice J: *"synchronous where the tier allows, else queued
 * with the honest 'undoing — takes a few minutes' state"*. This is the second
 * half; the first is `Account\SiteChanges` calling
 * {@see SiteChanges::revertById()} straight through for a tier whose undo
 * reaches nobody.
 *
 * ⛔ **IT REVERTS NOTHING ITSELF — IT HANDS THE ROW TO
 * {@see SiteChanges::revert()}, WHICH IS THE PATH THE AUTOMATIC ROLLBACK TAKES**
 * (5801, and §2.11.3's J row). The screen adds no second revert mechanism and
 * neither does this. What differs between an owner undo and an auto-revert is
 * one enum on the row and the actor in the audit line — never the code that
 * runs.
 *
 * ⛔ **NOT AN `AutopilotJob`, ON `MeasureSiteChangeJob`'s ARGUMENT (5814).** That
 * base class is the contract for the 142 catalog automations — an
 * `automationKey()` naming a row in doc `16`, rule 44's `execute()`/`handoff()`
 * pair, an `automation_runs` row an owner is shown. **This is not an automation
 * at all**: a person pressed a button. There is no provider-free variant to hand
 * off to, `handoff()` could only be an empty method — the stub rule 44 forbids —
 * and an `automation_runs` row would file a customer's own decision as
 * something the platform did on its own.
 *
 * ⛔ **AND IT KEEPS NONE OF THAT CLASS'S GATES, WHICH IS A DEPARTURE FROM
 * `MeasureSiteChangeJob` AND IS THE POINT** (5838). That job skips a paused or
 * suspended tenant because measuring is actuation the platform chose to do.
 * **This is the owner asking us to stop.** A pause, a suspension, an unpaid
 * invoice or `actuation.enabled` being off are all reasons not to put more of
 * our work onto somebody's website, and **none of them is a reason to refuse to
 * take ours back off** — 5813's argument, one rung further: refusing would leave
 * our content on a website whose owner has explicitly asked for it gone, with no
 * code path to remove it.
 *
 * ## What happens when the site says no
 *
 * ⚠️ **THE REQUEST IS CLEARED AND AN OWNER ACTION ITEM IS FILED**, on
 * `ChangeMeasurer::putItBack()`'s precedent for the identical situation. A card
 * that said *"undoing — a few minutes"* for ever would be the state 5759
 * describes wearing the fix for it, and a refusal nobody is told about is a
 * product that has quietly stopped working (3791).
 *
 * ⚠️ **AN ADAPTER REFUSAL IS NOT AN EXCEPTION AND DOES NOT RETRY.** `tries`
 * covers a process that died — a deploy, an out-of-memory — not a website that
 * answered no. Retrying silently for half an hour behind a card reading
 * *"undoing"* would be the platform deciding how long to keep somebody waiting;
 * the owner asked once, gets an answer, and presses again if they want to.
 *
 * ## What happens when this job dies rather than the site saying no
 *
 * ⛔ **THE PARAGRAPH ABOVE WAS RIGHT ABOUT THE REFUSAL AND SILENT ABOUT THE
 * THROW, AND THE THROW WENT NOWHERE AN OWNER COULD SEE — CORRECTED 2026-08-25
 * (9580, 9581).** `handle()` announces a refusal because `revert()` **returns**
 * one. A thrown exception returns nothing: {@see self::handle()} never reaches
 * {@see SiteChanges::clearUndoRequest()}, no `OwnerActionNeeded` is filed, the
 * two attempts are spent and the row lands in `failed_jobs` — **which nothing
 * on this platform reads on behalf of a customer**. `checkFailedJobs()` is the
 * only alerting reader of that table and wants twenty-five rows in an hour;
 * `jobs:prune-failed` deletes it after thirty days.
 *
 * ⛔ **AND THE HOUR MADE IT WORSE RATHER THAN BETTER.**
 * {@see SiteChanges::UNDO_IN_PROGRESS_MINUTES} exists to stop a card reading
 * *"a few minutes"* for ever, so the owner watched *"we are putting this page
 * back"* for an hour and then saw the **Undo button offer itself again with no
 * explanation** — 5849's exact defect, arriving through the ceiling written to
 * bound a different one, on a change that is still on their website.
 *
 * ✅ **{@see self::failed()} FILES THE SAME FACT THE REFUSAL FILES**, so the
 * card reads {@see SiteChangeUndoState::UndoDidNotLand} — *"We tried … and
 * could not, so our change is still on it. You can try again"* — within seconds
 * of the last attempt rather than an hour later with nothing said. ⛔ **A read
 * and not a column** (6062): the failure already writes a row and
 * {@see SiteChanges::history()} already reads it back.
 */
final class UndoSiteChangeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Two attempts, for a process that died rather than for a site that
     * refused — see the docblock.
     */
    public int $tries = 2;

    /**
     * Five minutes, then half an hour — each spread by ±25%.
     *
     * ⛔ **A FIXED LADDER WAS A METHOD, NOT A PROPERTY, WAITING TO HAPPEN**
     * (6267). `public array $backoff = [300, 1800]` retries every queued
     * measurement of every tenant against the same customer's website at the
     * same instant, which is exactly the burst
     * {@see OutboundSiteBudget} then has to refuse. Laravel
     * prefers a `backoff()` method over the property, and `29` §2 rule 40 and
     * `CLAUDE.md` §Engineering both say *"retried with backoff"*. **The jitter
     * is this codebase's own** — neither document names it, and it is what stops
     * the ladder from becoming the burst.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.actuation_seconds'));
    }

    /**
     * ⚠️ **IDS AND AN ACTOR KIND RATHER THAN MODELS OR AN `ActuationActor`.**
     * `SerializesModels` re-queries on unserialize with no tenant in context, so
     * a model property resolves to nothing; and `ActuationActor`'s constructor is
     * private by design (it exists so *"the owner did this"* cannot be recorded
     * without saying which owner), so it is rebuilt here from the two halves
     * rather than serialised whole.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $locationId,
        public readonly int $siteChangeId,
        public readonly SiteChangeActor $actorKind,
        public readonly ?int $actorUserId,
    ) {}

    public function handle(SiteChanges $siteChanges, ActivityService $activity): void
    {
        Tenancy::set($this->businessId);

        $outcome = $siteChanges->revertById($this->siteChangeId, $this->actor(), $this->reason());

        if ($outcome !== null && $outcome->ok) {
            return;
        }

        $siteChanges->clearUndoRequest($this->siteChangeId);

        // ⚠️ **ONE ITEM PER PRESS, WHICH IS WHAT MAKES IT SAFE TO BE
        // UNCONDITIONAL** (5746's predicate, from the other side). The nightly
        // automatic retry files at most one item ever, because it runs on its
        // own schedule; this runs only when a person pressed a button, so an
        // item per attempt is an item per thing the person did.
        $activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $this->locationId,
            [
                'automation' => 'actuation.undo_site_change',
                'site_change_id' => $this->siteChangeId,
                'detail' => $outcome === null
                    ? 'the change could not be loaded'
                    : $outcome->detail,
            ],
        );
    }

    /**
     * ⚠️ **STAFF AND OWNER STAY TOLD APART ACROSS THE QUEUE** (5751). A support
     * agent acting through an impersonation session is exactly the case that
     * would otherwise be filed as the owner's own decision, and
     * `site_changes.rolled_back_by` is where that difference has to survive.
     */
    private function actor(): ActuationActor
    {
        return match ($this->actorKind) {
            SiteChangeActor::Autopilot => ActuationActor::autopilot(),
            SiteChangeActor::Owner => ActuationActor::owner((int) $this->actorUserId),
            SiteChangeActor::Staff => ActuationActor::staff((int) $this->actorUserId),
        };
    }

    /**
     * Every attempt is spent and none of them reached an answer: say so where
     * the owner is already looking.
     *
     * ⛔ **THE SAME TWO ACTS `handle()` PERFORMS ON A REFUSAL, FOR THE SAME
     * REASON** (6062, and `ChangeMeasurer::putItBack()`'s precedent). The
     * request is taken back because nobody is coming, and an owner action item
     * naming the change set is filed because that row **is** the screen state:
     * `SiteChanges::history()` reads it and the card becomes
     * {@see SiteChangeUndoState::UndoDidNotLand}, which keeps the button.
     *
     * ⛔ **AND IT IS NOT A SECOND RECORD BESIDE `failed_jobs`** (9370's
     * refusal). The framework's row is for whoever debugs the exception; this is
     * the sentence the person who pressed the button is owed, on the screen they
     * pressed it on. Neither can stand in for the other, and no counter, no
     * threshold and no registry key is added here.
     *
     * ⚠️ **GUARDED ON THE CHANGE STILL BEING LIVE**, which is
     * {@see CaptureInboundMediaJob::failed()}'s `alreadyCaptured()`
     * at a second address. An attempt that reverted the page and then died —
     * a worker killed between the commit and the return, a `MaxAttemptsExceeded`
     * on a job that had already done its work — must not be told to the owner as
     * a failure, because `rolled_back_at` is set and the card already says
     * *Undone*.
     *
     * ⚠️ **WHAT THIS CANNOT KNOW IS STATED RATHER THAN GUESSED.** A throw from
     * inside the adapter leaves us genuinely unable to say whether the write
     * landed; `revert()` records nothing until the adapter has answered `ok`, so
     * the row is the only thing we have and the row says the change is still
     * there. **The sentence the owner reads is what we know**, and pressing again
     * is safe — an unpublish of a page that is already down is an ordinary
     * success on this platform.
     *
     * ⚠️ **THE EXCEPTION'S CLASS AND NOTHING ELSE.** `detail` is a free-text bag
     * that `OwnerAttention` deliberately never renders, and an exception
     * *message* on this path can carry a URL, a plugin's error page or a
     * fragment of the customer's own website — `SiteChanges::auditContext()`'s
     * rule about field names rather than values, one file over.
     */
    public function failed(?Throwable $e): void
    {
        Log::warning('An owner undo did not finish after every attempt.', [
            'business_id' => $this->businessId,
            'site_change_id' => $this->siteChangeId,
            'reason' => $e === null ? 'unknown' : $e::class,
        ]);

        Tenancy::actingAs($this->businessId, function () use ($e): void {
            $siteChanges = app(SiteChanges::class);

            if (! $siteChanges->isStillLive($this->siteChangeId)) {
                return;
            }

            $siteChanges->clearUndoRequest($this->siteChangeId);

            app(ActivityService::class)->record(
                AutopilotActionType::OwnerActionNeeded,
                $this->locationId,
                [
                    'automation' => 'actuation.undo_site_change',
                    'site_change_id' => $this->siteChangeId,
                    'detail' => 'the undo did not finish: '.($e === null ? 'unknown' : $e::class),
                ],
            );
        });
    }

    /**
     * What goes in `rolled_back_reason`.
     *
     * ⚠️ **ONE STRING, SHARED WITH THE SYNCHRONOUS PRESS.**
     * {@see SiteChanges::ownerUndoReason()} holds it and its argument, because
     * a T3 undo runs inside the owner's own request and a T1 undo runs here —
     * and two spellings would put two explanations on the same act depending on
     * which rung a tenant's website is on.
     */
    private function reason(): string
    {
        return SiteChanges::ownerUndoReason($this->actor());
    }
}
