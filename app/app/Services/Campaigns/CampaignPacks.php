<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Enums\CampaignAudience;
use App\Enums\CampaignKind;
use App\Exceptions\CampaignRefused;
use App\Exceptions\MessageCannotBeComposed;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\CampaignPack;
use App\Models\Location;
use App\Services\Messaging\Composer\ReactComposer;
use App\Support\Campaigns\CampaignPackCatalog;
use App\Support\Campaigns\PackMessage;
use App\Support\Campaigns\PackPreview;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The gallery of authored campaign packs, and the one way one becomes a
 * tenant's campaign — T296 §B, CC-5 §1.
 *
 * ## ⛔ THERE IS NO NEW SEND PATH HERE, AND THAT IS THE WHOLE DESIGN
 *
 * Turning a pack on **drafts campaigns**. Nothing else. From that moment the
 * message is a `campaigns` row and every existing gate applies to it unchanged,
 * because they are the same rows `RunCampaignJob` has always read:
 *
 *   `Campaigns::confirm()`        CONFIRM — the first send of a new campaign
 *                                 type is one of its three reserved cases, so a
 *                                 pack cannot activate itself into sending.
 *   `ConsentService::decide()`    consent, `opt_outs`, `compliance_suppressions`,
 *                                 `state_messaging_rules` and the recipient-local
 *                                 quiet window, asked per recipient with
 *                                 `OutreachPurpose::Marketing` (2100).
 *   `SendCollisionArbiter`        `S1` — one message a day reaches a contact.
 *   `BroadcastPreconditions`      3310's three, when the kind demands the
 *                                 tenant's own brand and number.
 *   `SendCredits` / `SendingGuard` the balance, the pauses and the halt.
 *
 * ⚠️ **SO THE COMPLIANCE ASSERTION FOR THIS SLICE IS DRIVEN THROUGH THE RUNNER
 * AND NOT THROUGH THIS CLASS** — `tests/Feature/Campaigns/CampaignPackTest.php`
 * enrols a suppressed contact and an unsuppressed one on a pack-drafted campaign
 * and runs the real job. Asserting here that "we call the pipeline" would be
 * 398's shape from the inside: a claim about a guard, made by the thing that is
 * meant to be guarded.
 *
 * ## ⚠️ ACTIVATION LEAVES EVERY CAMPAIGN IN `Draft`
 *
 * A pack is twelve cards, not an approval. `CONFIRM` is reserved for exactly
 * three things and *"the first send of a new campaign type"* is one of them, so
 * confirming on the tenant's behalf because they clicked **Turn it on** would
 * be this class signing the approval that `campaign.confirmed` exists to
 * attribute to a person. T296 §B4's *"Turning ON walks the same `canSend`
 * chokepoint as everything else"* is satisfied by the campaign rows; the
 * approval is still somebody's.
 *
 * ## ⚠️ ONE CAMPAIGN PER MESSAGE, AND THE DAY OFFSET IS NOT SCHEDULED
 *
 * `campaigns` holds one `body_template`, so a three-message pack is three
 * campaigns. Their day offsets travel in the drafted campaign's **name** and
 * nowhere else, because `campaigns.scheduled_for` has no reader anywhere in
 * `app/` — writing a date into a column nothing consults would be
 * `CLAUDE.md`'s first recurring failure in the slice whose notes cite it.
 * **Nothing in this application delays the second message of a pack**, and that
 * is stated rather than implied: what a pack gives a tenant today is authored
 * copy and a drafted campaign per rung, not a scheduler. Decision 5254.
 */
final class CampaignPacks
{
    public function __construct(
        private readonly Campaigns $campaigns,
        private readonly ReactComposer $composer,
    ) {}

    /**
     * Every pack, in the owner's order.
     *
     * ⚠️ **`position` AND THEN `key`.** Two packs given the same position by a
     * bad seed would otherwise come back in whatever order the planner chose,
     * so a gallery would reshuffle itself between page loads.
     *
     * @return Collection<int, CampaignPack>
     */
    public function gallery(): Collection
    {
        return CampaignPack::query()
            ->orderBy('position')
            ->orderBy('key')
            ->get();
    }

    public function find(string $key): ?CampaignPack
    {
        return CampaignPack::query()->where('key', $key)->first();
    }

    /**
     * Load `CampaignPackCatalog` into `campaign_packs` — `php artisan packs:sync`.
     *
     * A command rather than a migration, on `SyncDefaultsRegistry`'s three
     * reasons unchanged: a migration runs once, a rollback would delete rows it
     * did not write, and the seed has to be re-runnable against a live database.
     *
     * ## ⛔ THE CATALOGUE IS AUTHORITATIVE, WHERE `offers:sync` AND `legal:seed`
     * DELIBERATELY ARE NOT
     *
     * Both of those refuse to touch a row that already exists, and each has a
     * concrete thing it is protecting: `PlanOffers::sync()` protects an
     * operator's `closes_at` edit — refreshing would reopen a closed window on
     * the next deploy, silently — and `SeedLegalDrafts` protects counsel's
     * published text, which a seed must never move back.
     *
     * **This table has neither.** There is no Ops editor for a pack, no tenant
     * writes one, and a chokepoint lint holds this class as the only file in
     * `app/` permitted to touch the model — so the catalogue is the only thing
     * that has ever written a row, and refusing to update would mean a fixed
     * typo could never reach an installed system. The trade is stated rather
     * than inherited: **a pack edited by hand in the database is overwritten on
     * the next deploy**, and the supported way to change a pack's words is to
     * change this catalogue. That is T296 §B1's mirror law with the editor
     * unbuilt — *"the pack editor is the one place words change"*, and today the
     * catalogue is the pack editor.
     *
     * @return list<string> the key of every pack written or moved
     */
    public function sync(bool $dryRun = false): array
    {
        $written = [];

        DB::transaction(function () use ($dryRun, &$written): void {
            foreach (CampaignPackCatalog::packs() as $pack) {
                // ⚠️ **PARSED BEFORE IT IS STORED.** A catalogue message with a
                // `{Name}` in it is a send that arrives with braces showing, and
                // the cheapest place to find that out is the deploy that would
                // have introduced it — not the first preview, and certainly not
                // the first send.
                foreach ($pack['messages'] as $message) {
                    PackMessage::fromArray($message);
                }

                $existing = CampaignPack::query()->where('key', $pack['key'])->first();

                if ($existing instanceof CampaignPack && ! $this->differs($existing, $pack)) {
                    continue;
                }

                $written[] = $pack['key'];

                if ($dryRun) {
                    continue;
                }

                CampaignPack::query()->updateOrCreate(['key' => $pack['key']], $pack);
            }
        });

        return $written;
    }

    /**
     * What this pack would send, message by message.
     *
     * T296 §B2: *"Preview the messages"* before *"Turn it on"*. Each entry
     * carries the authored template with its slots visible **and** the exact
     * bytes a handset receives — see {@see PackPreview} for why both.
     *
     * @param  ?string  $link  A short link already minted on the owned domain,
     *                         or null. The same value `activate()` will be
     *                         given: a preview composed without the link a send
     *                         will carry is a preview of a different message,
     *                         and a shorter one.
     * @return list<PackPreview>
     *
     * @throws CampaignRefused when the pack has no authored copy, or one of its
     *                         messages cannot be composed at all
     */
    public function preview(CampaignPack $pack, ?Location $location = null, ?string $link = null): array
    {
        $business = $this->businessNameFor($location);

        return array_map(
            function (PackMessage $message) use ($business, $link, $pack): PackPreview {
                try {
                    $composed = $this->composer->compose(
                        template: $message->body,
                        businessName: $business,
                        // ⚠️ **NO CONTACT, WHICH IS `Campaigns::probe()`'s
                        // CHOICE AND ITS REASON.** A preview built around one
                        // real customer's name would show a length that is true
                        // for that person and false for the audience.
                        contactName: null,
                        link: $link,
                    );
                } catch (MessageCannotBeComposed $e) {
                    throw CampaignRefused::because(sprintf(
                        'Day %d of the "%s" pack cannot be sent as written, and it would fail the '
                        .'same way for every recipient: %s',
                        $message->dayOffset,
                        $pack->name,
                        $e->getMessage(),
                    ));
                }

                return new PackPreview($message->dayOffset, $message->body, $composed);
            },
            $this->authoredMessages($pack),
        );
    }

    /**
     * Turn a pack on: one drafted campaign per message, approved by nobody yet.
     *
     * @param  string  $actor  Who asked. Recorded by `Campaigns::draft()` on
     *                         every campaign this writes.
     *
     * ⚠️ **THE AUDIENCE IS A PARAMETER AND THE CONTACTS ARE NOT.** Which people
     * a `ManualSelect` campaign reaches is `Campaigns::enrol()`'s to be told,
     * after somebody has confirmed it — a pack that carried a contact list would
     * be choosing an audience for a campaign nobody had approved yet, which is
     * the ordering that method refuses in as many words.
     *
     * ⛔ **THIS METHOD HAS NO CALLER IN `app/` — 11520–11524, 2026-08-28.**
     * `packs:sync` reaches {@see self::sync()} and nothing else, so `gallery()`,
     * `preview()` and this are reachable only from `tests/`, and
     * {@see Campaigns::draft()} — whose one caller is the line below — is
     * reachable only through here. **So the whole chain from a pack to a
     * campaign row is unwired**, and `Campaigns::enrol()` carries the
     * measurement, the escape hatches that were checked, and why `@uncalled` is
     * refused on all of it.
     *
     * ⚠️ **AND IT WOULD REFUSE EVERY SHIPPED PACK IF IT WERE CALLED** — see
     * {@see self::authoredMessages()} and 5253: all twelve carry `messages =
     * []`, so this is a second, independent absence stacked on the first, and
     * fixing either alone changes nothing a tenant can see. `/features` says
     * *"Twelve campaigns, already written. Preview the exact messages, then
     * turn one on"* behind the seeded-false `features.campaigns` row; **that
     * claim needs both halves before the row may be flipped**, and nothing in
     * this application checks that it has them.
     * @return list<Campaign>
     *
     * @throws CampaignRefused when the pack has no authored copy
     */
    public function activate(
        CampaignPack $pack,
        CampaignAudience $audience,
        string $actor,
        ?Location $location = null,
        ?string $link = null,
        CampaignKind $kind = CampaignKind::Reactivation,
    ): array {
        Tenancy::idOrFail();

        $messages = $this->authoredMessages($pack);

        // ⚠️ **COMPOSED BEFORE ANYTHING IS WRITTEN.** `Campaigns::confirm()`
        // proves a template one campaign at a time, so without this a pack whose
        // fourth message is too long would draft three good campaigns and one
        // that can never be confirmed — and the tenant would find out on the
        // fourth screen. This is the same probe, run for the whole pack, at the
        // moment somebody is standing in front of it.
        $this->preview($pack, $location, $link);

        return DB::transaction(fn (): array => array_map(
            fn (PackMessage $message): Campaign => $this->campaigns->draft(
                name: $this->campaignName($pack, $message, count($messages)),
                bodyTemplate: $message->body,
                audience: $audience,
                actor: $actor,
                location: $location,
                link: $link,
                kind: $kind,
            ),
            $messages,
        ));
    }

    /**
     * Whether the stored row and the catalogue disagree about anything.
     *
     * ⚠️ **`toEqual`'s RULE IN CODE, FOR THE SAME REASON `CLAUDE.md` GIVES IT
     * FOR TESTS.** `messages` comes back from jsonb, and a `===` between a
     * decoded list and a PHP literal is comparing two things a database is free
     * to have re-encoded. The comparison is on the canonical `PackMessage`
     * shape, so a row that says the same thing in a different spelling does not
     * report as changed on every deploy.
     *
     * @param  array{key: string, name: string, one_liner: string, messages: list<array{day_offset: int, body: string}>, position: int}  $pack
     */
    private function differs(CampaignPack $existing, array $pack): bool
    {
        $stored = array_map(
            static fn (PackMessage $message): array => $message->toArray(),
            $existing->orderedMessages(),
        );

        return $existing->name !== $pack['name']
            || $existing->one_liner !== $pack['one_liner']
            || $existing->position !== $pack['position']
            || $stored !== $pack['messages'];
    }

    /**
     * A pack's messages, or a refusal naming the pack.
     *
     * ⛔ **ALL TWELVE SHIPPED PACKS REFUSE HERE TODAY** — see
     * `CampaignPackCatalog`, which carries the argument: T296 §B3 authored names
     * and one-liners, the message bodies live in the SEQ packs of record, and
     * this codebase does not invent marketing copy that leaves over the GOAIEZ
     * 10DLC brand. **The refusal is deliberately loud and deliberately not a
     * silent empty list**: activating a pack and receiving zero campaigns back
     * is indistinguishable from activating one that worked.
     *
     * @return list<PackMessage>
     *
     * @throws CampaignRefused
     */
    private function authoredMessages(CampaignPack $pack): array
    {
        if (! $pack->isAuthored()) {
            throw CampaignRefused::because(sprintf(
                'The "%s" pack has no messages written for it yet, so there is nothing to preview '
                .'and nothing to turn on. The twelve packs ship with their names and their promises '
                .'and without their copy, because the message bodies are the owner\'s to supply — '
                .'this application will not invent a marketing text that leaves over the GO AI EZ '
                .'carrier registration.',
                $pack->name,
            ));
        }

        try {
            return $pack->orderedMessages();
        } catch (InvalidArgumentException $e) {
            throw CampaignRefused::because(sprintf(
                'The "%s" pack is stored in a shape this application cannot send: %s',
                $pack->name,
                $e->getMessage(),
            ));
        }
    }

    /**
     * What the drafted campaign is called on the tenant's own list.
     *
     * ⚠️ **THE DAY OFFSET IS IN THE NAME BECAUSE IT IS NOWHERE ELSE** — see the
     * class docblock. A tenant looking at three campaigns from one pack needs to
     * know which is which, and the alternative was a column nothing reads.
     * A single-message pack takes the pack's own name unadorned, because
     * "Post-job care — day 0 of 1" is noise.
     */
    private function campaignName(CampaignPack $pack, PackMessage $message, int $total): string
    {
        if ($total === 1) {
            return $pack->name;
        }

        return sprintf('%s — day %d', $pack->name, $message->dayOffset);
    }

    /**
     * Who the preview says the message is from.
     *
     * `Campaigns::probe()`'s rule, reproduced because a pack has no campaign row
     * to read it off yet: the location's business name where there is a
     * location, and the tenant's own name where there is not.
     */
    private function businessNameFor(?Location $location): string
    {
        if ($location instanceof Location) {
            return $location->businessName();
        }

        return (string) Business::query()->find(Tenancy::idOrFail())?->name;
    }
}
