<?php

declare(strict_types=1);

namespace App\Jobs\Content;

use App\Enums\AutopilotActionType;
use App\Jobs\AutopilotJob;
use App\Models\Location;
use App\Services\Actuation\SiteChangeQuarantines;
use App\Services\Actuation\SiteChanges;
use App\Services\Content\ContentEvidence;
use App\Services\Content\Publishing;

/**
 * Master 12.4's publishing pipeline, steps 3–7 (`BUILD-PLAN` §2.11.3 slice D).
 *
 * **gate → AUTO-WITH-HOLD 24h → snapshot → apply through `CmsAdapter` → say so.**
 *
 * ⛔ **THE GATES THIS JOB DOES NOT CHECK ARE THE POINT OF EXTENDING
 * `AutopilotJob`.** The kill switch, the tenant's own pause (row 5's *"all
 * sending + actuation off"* — this chain is the actuation half it has been
 * waiting for), a compliance suspension and the idempotency claim all run in
 * `handle()` **before any side effect**, and every one of them records a
 * `skipped` run rather than a failure. Re-checking any of them here would be
 * 398's shape: two guards for one rule, with the inner one unfalsifiable.
 *
 * ⛔ **`execute()` AND `handoff()` ARE ONE IMPLEMENTATION AND THE FORK IS ONE
 * BOOLEAN** (`CLAUDE.md` rule 44, `41` Part 1). Both run every gate; the last
 * step either opens a change set or hands the owner the same gated copy to
 * paste. Two methods calling {@see Publishing::attempt()} with a different
 * argument is what stops the weaker rung drifting into a worse version of the
 * stronger one.
 *
 * ⚠️ **THE CANDIDATE COMES FROM SOMEWHERE ELSE AND MUST KEEP DOING SO**
 * (`BUILD-PLAN` §2.11.5 conflict 5). Master 12.4's DIAGNOSE and the content
 * engine are Stage 5; this job publishes a `growth_pages` row a fixture, a
 * person or a later generator produced. Building a generator here to satisfy a
 * gate verb absorbs row 12.
 */
final class PublishGrowthPageJob extends AutopilotJob
{
    /**
     * The `site_changes.change_type` this pipeline writes.
     *
     * ⛔ **THE SAME LITERAL `Publishing::writeToTheSite()` PUTS ON THE CHANGE
     * SET, AND A LINT HOLDS THE TWO TOGETHER** (`Architecture\ActuationTest`).
     * They are two files apart and a quarantine keyed on a string one of them
     * stopped using would refuse nothing at all, silently and for ever — 272's
     * shape reached by a rename rather than by an omission.
     */
    public const string CHANGE_TYPE = 'growth_page';

    private ?bool $writesToSite = null;

    private bool $spent = false;

    public function __construct(
        int $businessId,
        int $locationId,
        public readonly int $growthPageId,
        public readonly ContentEvidence $evidence = new ContentEvidence,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'content.publish_growth_page';
    }

    /**
     * ⛔ **ONE CLAIM PER PAGE, NOT PER DISPATCH.** The lapsed-hold sweep runs
     * hourly and re-dispatches every page whose window has closed, so without
     * this the same page would be gated — and moderated, and debited — once an
     * hour until something changed.
     */
    protected function idempotencyKey(): string
    {
        return 'growth-page:'.$this->growthPageId;
    }

    /**
     * ⛔ **THE CLAIM IS HANDED BACK UNLESS THE PAGE IS PUBLISHED OR HANDED
     * OVER**, which is the opposite of the base class's default and is argued
     * rather than inherited (the lint requires every keyed job to answer). The
     * default keeps the claim because *"losing a send is recoverable; sending
     * twice is not"* — and that is the right call for a message to a stranger.
     * Here the irreversible act is the site write, and it is already protected
     * by `growth_pages.status` becoming terminal and by
     * {@see SiteChanges::apply()} refusing an
     * already-applied change set. What a kept claim *would* cost is the ordinary
     * case: a page refused because its hold is still open, or because the mail
     * system was down for an hour, could never be attempted again.
     */
    protected function claimIsSpent(): bool
    {
        return $this->spent;
    }

    /**
     * ⛔ **THREE CONDITIONS, AND THE THIRD IS A FACT RATHER THAN A PROMISE**
     * (5665). `actuation.enabled` is the operator's half; the adapter's own
     * health is the code's. On every deployment that exists today the bound
     * adapter answers *"this deployment writes to no website"*, so the full path
     * is closed whatever the registry says — which is what makes *"nothing
     * actuates a real site until slice H can revert one"* true rather than
     * asserted.
     */
    protected function canExecute(): bool
    {
        $location = $this->location();

        if (! $location instanceof Location) {
            return false;
        }

        $writesToSite = $this->writesToSite ??= app(Publishing::class)->canWriteToSite($location);

        // ⛔ **SLICE H's QUARANTINE, AND IT IS ASKED LAST ON PURPOSE.** A fix
        // that was measured over a month and found to have made *this* site
        // worse does not get retried onto it (`BUILD-PLAN` §2.11.3 H: *"a sick
        // change rests"*). Asked after `canWriteToSite()` rather than before it
        // so that this is the thing refusing rather than an outer guard that
        // already had — 398's shape is what makes a gate unfalsifiable, and on
        // every deployment that exists the outer answer is already false.
        //
        // ⚠️ **REFUSING HERE FALLS TO `handoff()`, WHICH IS THE RIGHT ANSWER
        // AND NOT A CONSOLATION** (5745). A quarantine is a statement about what
        // *this platform* writes to somebody's website; the owner is still
        // handed the same gated copy to publish themselves if they want it. The
        // site-writing path is gated absolutely and the paste-it-yourself path
        // is not.
        if ($writesToSite
            && app(SiteChangeQuarantines::class)->isQuarantined($this->locationId ?? 0, self::CHANGE_TYPE)) {
            return $this->writesToSite = false;
        }

        return $writesToSite;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->run(writesToSite: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->run(writesToSite: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function run(bool $writesToSite): array
    {
        $outcome = app(Publishing::class)->attempt(
            $this->growthPageId,
            $this->evidence,
            $writesToSite,
            $this->automationKey(),
        );

        $this->spent = $outcome->published || $outcome->advisory !== null;

        return $outcome->forRunOutput();
    }

    /**
     * ⚠️ **NOTHING GENERIC IN THE FEED.** `Publishing` files the sentences that
     * are true of what happened — *"Published a page to your website"* through
     * the change set, or an action item when the owner has to paste it — and the
     * base class's *"an automation finished"* on top of either is the noise
     * `AutopilotJob`'s own docblock warns makes the feed worse.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + ['growth_page_id' => $this->growthPageId];
    }
}
