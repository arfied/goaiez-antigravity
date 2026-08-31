<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Enums\CampaignAudience;
use App\Enums\CampaignKind;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Exceptions\CampaignRefused;
use App\Exceptions\MessageCannotBeComposed;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Messaging\Composer\ReactComposer;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one place a campaign is written, confirmed and enrolled — a reactivation
 * (T137 `SL-2`, lane `L3`) or an SMS broadcast (decision 3310).
 *
 * Sending is `App\Jobs\RunCampaignJob`'s; this decides *who* and *whether*, and
 * every decision it makes is recorded before a single message can leave.
 *
 * ⛔ **3310'S THREE PRECONDITIONS ARE NOT ASKED HERE, AND THAT IS THE RULING
 * RATHER THAN AN OMISSION.** *"Three preconditions, all of which must be checked
 * at send time and not at configure time."* A tenant whose 10DLC filing clears
 * next Tuesday may draft and confirm a broadcast today; what they cannot do is
 * send one before it clears, and `BroadcastPreconditions` is asked per recipient
 * for exactly that reason.
 *
 * ## CONFIRM, and why T137 does not remove it
 *
 * ⛔ **DECISION 2106.** T137's *"GATE NOTHING — every SL feature ships LIVE"* is
 * about flip-gates, and CONFIRM is a different mechanism with three reserved
 * cases — GBP identity changes, anything that spends money, and **the first send
 * of a new campaign type**. A campaign spends credits *and* is a new campaign
 * type, so it is squarely inside two of the three. `confirm()` is the only way
 * out of `Draft`, the columns it writes are guarded on the model, and a CHECK
 * refuses a sendable status without them — three layers, and the claim is made
 * here only because all three exist.
 *
 * ## The template is proved before anybody is enrolled
 *
 * ⚠️ **A CAMPAIGN THAT CANNOT COMPOSE FAILS IDENTICALLY FOR EVERY RECIPIENT**,
 * so discovering it in the runner means five thousand failures that all say the
 * same thing. `confirm()` composes a probe message — the real business name, the
 * real link, no contact name — and refuses the confirmation if it will not fit
 * or the link is on a host we do not own. That is the moment a person is
 * standing in front of it.
 *
 * ⚠️ **AND IT IS A PROBE, NOT A PROOF.** A long first name can still push a
 * message over the budget at send time, and that recipient is refused
 * individually rather than the campaign being stopped. The probe catches the
 * template mistake; only the send can catch the name.
 */
final class Campaigns
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ReactComposer $composer,
        private readonly DormancySegment $dormancy,
    ) {}

    /**
     * Draft a campaign. Nothing is sendable until `confirm()`.
     *
     * @param  ?string  $link  A short link already minted on the owned domain,
     *                         or null. ⚠️ Not validated here: `confirm()` proves
     *                         it against a real composition, and validating in
     *                         two places is how the two spellings drift.
     * @param  CampaignKind  $kind  ⛔ **THE WRITER FOR `campaigns.kind`, AND IT
     *                              IS DELIBERATELY THE ONLY ONE** — a column
     *                              nothing sets is `CLAUDE.md`'s first recurring
     *                              failure, and a broadcast guard reading it
     *                              would then be a permanently-false predicate
     *                              on a green suite. ⚠️ **Nothing about the kind
     *                              is checked here.** 3310's three preconditions
     *                              are send-time facts about the tenant and are
     *                              asked per recipient by
     *                              `App\Services\Campaigns\BroadcastPreconditions`;
     *                              refusing a draft on today's reading of a
     *                              filing that clears next week would be the
     *                              configure-time answer 3310 rules out.
     */
    public function draft(
        string $name,
        string $bodyTemplate,
        CampaignAudience $audience,
        string $actor,
        ?Location $location = null,
        ?string $link = null,
        ?string $baseImagePath = null,
        CampaignKind $kind = CampaignKind::Reactivation,
    ): Campaign {
        Tenancy::idOrFail();

        $campaign = Campaign::query()->create([
            'name' => $name,
            'body_template' => $bodyTemplate,
            'audience' => $audience,
            'kind' => $kind,
            'status' => CampaignStatus::Draft,
            'location_id' => $location?->getKey(),
            'link' => $link,
            'base_image_path' => $baseImagePath,
            'created_by' => $actor,
        ]);

        $this->audit->record('campaign.drafted', $actor, $campaign, [
            'audience' => $audience->value,
            // ⚠️ **RECORDED AT THE DRAFT, BECAUSE THIS IS THE DECISION THE
            // APPROVER IS APPROVING.** `campaign.confirmed` names the person who
            // signed off; without the kind beside it, the append-only record
            // cannot say whether what they signed off was a blast on the
            // tenant's own brand or a reactivation on ours.
            'kind' => $kind->value,
            'location_id' => $location?->getKey(),
            // ⚠️ **THE TEMPLATE IS NOT IN THE METADATA.** It is the tenant's own
            // marketing copy and it is one substitution away from carrying a
            // customer's name; `audit_log` is read by more people than the
            // campaign row is, and the row is one lookup away through the
            // entity reference. `ConsentService::record()` keeps proof out of
            // the log for the same reason.
            'has_link' => $link !== null,
            'has_image' => $baseImagePath !== null,
        ]);

        return $campaign;
    }

    /**
     * A person approves the first send. This is CONFIRM.
     *
     * @throws CampaignRefused
     */
    public function confirm(Campaign $campaign, string $actor): Campaign
    {
        $this->assertBelongsToTenant($campaign);

        if ($campaign->status !== CampaignStatus::Draft) {
            throw CampaignRefused::because(
                'Only a draft can be confirmed. Re-confirming a running campaign would record a '
                .'second approval for sends that had already gone out, which is the opposite of '
                .'what the record is for.'
            );
        }

        // ⚠️ **BEFORE THE APPROVAL IS WRITTEN, NOT AFTER.** A confirmation
        // recorded against a campaign that then refuses to compose is an
        // approval for something that never existed, and it is the row an
        // incident review would read as "a person signed this off".
        $this->probe($campaign);

        return DB::transaction(function () use ($campaign, $actor): Campaign {
            // Assigned rather than filled: both columns are guarded on the model
            // precisely so that nothing but this method can assert an approval.
            $campaign->confirmed_at = now();
            $campaign->confirmation_actor = $actor;
            $campaign->status = CampaignStatus::Scheduled;
            $campaign->save();

            // `29` §2 rule 42. The campaign row says a campaign exists; only
            // this says who approved sending it, which is the question asked
            // after a complaint.
            $this->audit->record('campaign.confirmed', $actor, $campaign, [
                'audience' => $campaign->audience->value,
                // ⛔ **WHAT THEY APPROVED, NOT MERELY THAT THEY APPROVED.** A
                // broadcast spends the tenant's own brand's reputation and a
                // reactivation spends ours (2101, 3310); an approval record that
                // cannot tell them apart cannot answer the question asked after
                // a complaint.
                'kind' => $campaign->kind->value,
            ]);

            return $campaign;
        });
    }

    /**
     * Materialise the audience into rows.
     *
     * ⚠️ **ENROLMENT IS NOT A SEND AND IS NOT A PERMISSION.** These rows say who
     * the campaign is *for*. Every one of them still goes through
     * `ConsentService::permit()` at send time, because consent, suppression and
     * the registers move between enrolment and the send — and decision 2099's
     * replacement rule is unconditional: no send without a recorded basis, with
     * STOP, HELP and suppression standing under both.
     *
     * ⚠️ **`firstOrCreate` AGAINST THE UNIQUE INDEX, NOT A COUNT-AND-INSERT.** A
     * second enrolment pass — a double-clicked launch, a retried job — must add
     * the newly-qualifying and touch nobody else. The index is what makes that
     * true; this method is only what makes it quiet.
     *
     * ## ⛔ THIS IS THE SOLE WRITER OF `campaign_recipients` AND IT HAS NO
     * ## CALLER IN `app/` — 11520–11524, 2026-08-28
     *
     * ⛔ **THE ANSWER 11366 LEFT OPEN IS *BUILT AND NEVER WIRED*, NOT
     * *DELIBERATELY ABSENT*.** That row raised the absence and said in terms
     * that it *"does not act on it either"*; 9160–9163 had raised it before.
     * The evidence is negative, so it was re-derived rather than inherited:
     * the `firstOrCreate` below is the only INSERT into `campaign_recipients`
     * anywhere in `app/`; **every other `enrol(` in `app/` is inside a
     * comment**, and no count of them is written here because this docblock is
     * itself one of them (11454's rule — state the property, delete the
     * figure); `Campaigns` is type-hinted in exactly
     * one place outside this namespace's own tests —
     * {@see CampaignPacks}'s promoted constructor property — and the only
     * method it reaches is {@see self::draft()}. {@see CampaignPacks::activate()},
     * the only caller of `draft()`, **has no caller of its own**;
     * `packs:sync` reaches `CampaignPacks::sync()` and nothing else.
     * `confirm()`, `pause()`, `resume()` and `cancel()` have no `app/` caller
     * either, and `ops:method-callers` scores **all four ALIVE by name
     * collision** with unrelated `confirm()`/`pause()`/`resume()`/`cancel()`
     * declarations elsewhere in the tree — so of this class's six public
     * methods the instrument reports exactly one, this one, and it is silent
     * about four dead ones. (`draft()` is the sixth and is genuinely alive,
     * from `CampaignPacks::activate()`, which is itself dead.) ⛔ **That is the
     * shortfall the command discloses on every run, landing on the whole
     * finding at once**, and it is why the answer above was derived by hand
     * rather than read off the report.
     *
     * ⛔ **AND *"NO CALLER"* IS A PROOF HERE RATHER THAN THE USUAL FLOOR**,
     * because the escape hatches that make it a floor were each checked and
     * each is empty in this tree: **no string-keyed container resolution
     * exists in `app/` at all** — `app('…')`, `resolve('…')` and `->make('…')`
     * are zero occurrences — **and no computed method dispatch exists in
     * `app/` at all**, so `$x->{$verb}()` and `call_user_func` cannot be
     * hiding one. No route names any of it, no `wire:` directive spells any of
     * it, and the one container binding in this namespace is
     * `CampaignContextResolver` → `CampaignReplyResolver`, a different class on
     * the inbound-reply path.
     *
     * ⛔ **IT IS DELIBERATELY NOT TAGGED `@uncalled`, AND THE TAG WOULD WORK.**
     * 9162 built that mechanism for a **deliberate** absence, and
     * `ops:method-callers --acknowledged` prints every note under the sentence
     * *"a note is a record of a ruling"*. **There is no ruling.** Nothing in
     * `DECISIONS.md` argues that a campaign entry point should not exist: 2577
     * asserts the opposite as the premise of the row that built the sweep,
     * T137 `SL-2` lists the campaign engine among the things that *"ship
     * LIVE"*, and the commit that introduced this file calls itself *"a safety
     * commit on an interrupted lane, not a finished slice"*. A note here would
     * put a finding into the report that enumerates decided absences and turn
     * *"2 declarations carry a note"* into *"3"* — 9163's census corruption,
     * in the instrument built to answer this exact question. **The absence is
     * written here instead, where it cannot be read as a decision.**
     *
     * ⚠️ **WHAT IS OWED IS A TENANT-FACING ENTRY POINT, AND IT IS A WAVE OF
     * ITS OWN RATHER THAN A MISSING LIVEWIRE CLASS.** 3310's three
     * preconditions are send-time facts and are already asked per recipient by
     * {@see BroadcastPreconditions} on `RunCampaignJob`'s hot path, and every
     * recipient still goes through `ConsentService` there — **so the compliance
     * enforcement is downstream of the missing piece and an entry-point slice
     * cannot silently drop it.** What such a slice does have to carry is
     * CONFIRM, which {@see self::confirm()} is the only door to, and 5253's
     * twelve packs shipped with no authored copy.
     *
     * @param  ?list<int>  $customerIds  Required for `ManualSelect`, refused for
     *                                   `Dormant`. ⚠️ Not optional-by-default on
     *                                   purpose: a manual campaign that silently
     *                                   enrolled nobody looks exactly like one
     *                                   that ran and found nobody.
     *
     * @throws CampaignRefused
     */
    public function enrol(Campaign $campaign, ?array $customerIds = null): int
    {
        $this->assertBelongsToTenant($campaign);

        if ($campaign->confirmed_at === null) {
            throw CampaignRefused::because(
                'An unconfirmed campaign has no audience. Enrolling first would put a list of real '
                .'people behind a campaign nobody has approved, and the approval screen would then '
                .'be showing a decision that had already been half-made.'
            );
        }

        // ⛔ **`orderBy('id')` HERE IS WHAT MAKES `RunCampaignJob::batch()`'s OWN
        // ORDERING MEAN ANYTHING — wave 41 lane D (11085).** That method sorts
        // recipients by `id` and says in a comment that the ordering *"would
        // decide who gets messaged first"*. It does — and until this line the
        // ids it sorts were handed out **in whatever order Postgres returned
        // the customers**, because `audienceQuery()` carries no `ORDER BY`
        // anywhere and neither does {@see DormancySegment::apply()}. So the
        // sort was faithful rather than immune: it preserved an arbitrary order
        // exactly.
        //
        // ⚠️ **NOT A REORDERING OF WHO IS TEXTED — A DEFINITION OF IT.** Nobody
        // is added, nobody is dropped, and every recipient is still sent
        // eventually. What changes is that a campaign with more recipients than
        // `campaigns.batch_size` now sends to the **oldest customers first**
        // rather than to whichever rows the planner happened to hand back, and
        // that two enrolments of the same list produce the same order twice.
        //
        // ⚠️ **`id` RATHER THAN `created_at`, WHICH IS `batch()`'s OWN CHOICE
        // AND ITS OWN REASON** (289's shape): `created_at` is nullable and
        // Postgres sorts NULL first on a DESC ordering, so it would decide the
        // question on a column that may be empty.
        $customers = $this->audienceQuery($campaign, $customerIds)->orderBy('id')->get();
        $enrolled = 0;

        foreach ($customers as $customer) {
            $recipient = CampaignRecipient::query()->firstOrCreate([
                'campaign_id' => $campaign->getKey(),
                'customer_id' => $customer->getKey(),
            ], [
                'status' => CampaignRecipientStatus::Pending,
            ]);

            if ($recipient->wasRecentlyCreated) {
                $enrolled++;
            }
        }

        return $enrolled;
    }

    /**
     * Stop a running campaign where it stands. Resumable.
     */
    public function pause(Campaign $campaign, string $actor): void
    {
        $this->assertBelongsToTenant($campaign);

        if ($campaign->status !== CampaignStatus::Running && $campaign->status !== CampaignStatus::Scheduled) {
            return;
        }

        $campaign->status = CampaignStatus::Paused;
        $campaign->save();

        $this->audit->record('campaign.paused', $actor, $campaign);
    }

    /**
     * Start it again on the recipients it never reached.
     *
     * ⚠️ **NO SECOND CONFIRMATION, DELIBERATELY.** The approval is for *this
     * campaign's* sends and it was given once; asking again for the same sends
     * would make CONFIRM a thing people click through rather than read. What a
     * resume cannot do is change the template or the audience — those are a new
     * campaign, and a new campaign confirms.
     *
     * @throws CampaignRefused
     */
    public function resume(Campaign $campaign, string $actor): void
    {
        $this->assertBelongsToTenant($campaign);

        if ($campaign->status !== CampaignStatus::Paused) {
            throw CampaignRefused::because('Only a paused campaign can be resumed.');
        }

        if ($campaign->confirmed_at === null) {
            // Unreachable through this service and checked anyway: `status` is
            // one update() away from anywhere, and the CHECK underneath would
            // answer with a SQLSTATE nobody can explain.
            throw CampaignRefused::because('A campaign with no confirmation cannot be resumed.');
        }

        $campaign->status = CampaignStatus::Running;
        $campaign->save();

        $this->audit->record('campaign.resumed', $actor, $campaign);
    }

    /**
     * Stop it for good. The rows stay — they are the record of who was reached.
     */
    public function cancel(Campaign $campaign, string $actor): void
    {
        $this->assertBelongsToTenant($campaign);

        if ($campaign->status === CampaignStatus::Completed || $campaign->status === CampaignStatus::Cancelled) {
            return;
        }

        $campaign->status = CampaignStatus::Cancelled;
        $campaign->finished_at = now();
        $campaign->save();

        $this->audit->record('campaign.cancelled', $actor, $campaign);
    }

    /**
     * Who this campaign is for.
     *
     * ⚠️ **LOCATION SCOPING IS `S15` AND IT IS NOT COSMETIC.** A multi-location
     * tenant reactivating one branch must not text the other branch's customers:
     * the sending number is per location, the relationship the attestation
     * covers is per location, and a customer of the Memphis shop hearing from
     * the Nashville one is a stranger's text.
     *
     * @param  ?list<int>  $customerIds
     * @return Builder<Customer>
     *
     * @throws CampaignRefused
     */
    private function audienceQuery(Campaign $campaign, ?array $customerIds): Builder
    {
        $query = Customer::query();

        if ($campaign->location_id !== null) {
            $query->where('location_id', $campaign->location_id);
        }

        if ($campaign->audience === CampaignAudience::ManualSelect) {
            return $query->whereIn('id', $this->requireIds($customerIds));
        }

        if ($customerIds !== null) {
            throw CampaignRefused::because(
                'A dormant-segment campaign resolves its own audience. Passing a list beside it would '
                .'mean two answers to "who is this for", and the one that ran would be whichever the '
                .'code happened to prefer.'
            );
        }

        return $this->dormancy->apply($query);
    }

    /**
     * @param  ?list<int>  $customerIds
     * @return list<int>
     *
     * @throws CampaignRefused
     */
    private function requireIds(?array $customerIds): array
    {
        if ($customerIds === null || $customerIds === []) {
            throw CampaignRefused::because(
                'A manual-select campaign needs the contacts somebody selected. An empty selection '
                .'enrolling nobody is indistinguishable afterwards from a campaign that ran and found '
                .'nobody, which are two very different things to tell an owner.'
            );
        }

        return $customerIds;
    }

    /**
     * Compose the campaign once, with no contact, to prove the template.
     *
     * @throws CampaignRefused
     */
    private function probe(Campaign $campaign): void
    {
        $location = $campaign->location;
        $business = $location instanceof Location
            ? $location->businessName()
            : (string) $campaign->business?->name;

        try {
            $this->composer->compose(
                template: $campaign->body_template,
                businessName: $business,
                contactName: null,
                link: $campaign->link,
            );
        } catch (MessageCannotBeComposed $e) {
            throw CampaignRefused::because(
                'This campaign cannot be sent as written, and it would fail the same way for every '
                .'recipient: '.$e->getMessage()
            );
        }
    }

    /**
     * Refuse to act on somebody else's campaign.
     *
     * The read path is already scoped, so a stray campaign returns nothing. The
     * write path is not: `business_id` comes from the ambient tenant while the
     * campaign comes from the passed model, so tenant A could confirm tenant B's
     * campaign and the approval would be filed under a business that never gave
     * it. This is the *wrong tenant* case RLS cannot catch —
     * `ConsentService::assertBelongsToTenant()`'s reasoning, one table over.
     *
     * @throws CampaignRefused
     */
    private function assertBelongsToTenant(Campaign $campaign): void
    {
        if ($campaign->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw CampaignRefused::because(
            'That campaign belongs to another tenant. A confirmation written from here would be '
            .'filed under a business that never gave it.'
        );
    }
}
