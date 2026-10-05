<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\AutopilotActionType;
use App\Enums\CallRoutingMode;
use App\Enums\LiveAnswerMode;
use App\Models\SupportSetting;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Sms\TenantNumbers;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * How a tenant's calls reach us — conditional call forwarding, and the
 * first writer `support_settings` has ever had.
 *
 * ⛔ **`support_settings` SHIPPED ON 2026-07-30 AND NOTHING IN `app/` HAD
 * TOUCHED IT SINCE.** {@see SupportSetting} had a model, a factory and a full
 * set of call routing columns — `call_routing_mode`, `ring_timeout_seconds`,
 * `voicemail_greeting_type`, `on_call_numbers`, `forwarding_verified_at`,
 * `forwarding_broken_at` — and **no tenant had a row at all**. That is
 * `CLAUDE.md`'s first recurring failure shape (272), and the tell it names was
 * present exactly as described: an isolation test would have passed perfectly
 * against a table nothing writes, because there was nothing to leak.
 *
 * ⚠️ **THE ROW IS CREATED HERE, LAZILY, RATHER THAN AT PROVISIONING.** The
 * alternative was seeding one in `TenantProvisioner`, and it was
 * refused for the reason 2409 gives one service over: every column on this
 * table has a database default that is already the safe answer —
 * `call_routing_mode` is `tracking_only`, which `CallRoutingMode`'s own docblock
 * describes as *"their line rings exactly as it did before"*. A seeded row would
 * add a second place those defaults are written and nothing an owner could tell
 * apart, while a missing row and a seeded row mean the same thing to every
 * reader. **The absence of a row is therefore not a bug to backfill**, and
 * {@see self::modeFor()} answers `TrackingOnly` for a tenant who has one and for
 * a tenant who does not, identically.
 *
 * ⛔ **NOTHING HERE WRITES `forwarding_verified_at`, DELIBERATELY** (2913).
 * Verification means placing a real test call through the Infobip Voice API, and
 * that activation is the single external ask 2109 records as still outstanding —
 * *"no forwarding, no voicemail, no missed-call event"*. A timestamp stamped by
 * the save button would say a forward was confirmed to work by something that
 * has never dialled anything, and every screen and alert downstream reads that
 * column as evidence. **An unwritten column is a gap; a falsely written one is a
 * wrong answer**, and `forwarding_broken_at` — whose whole job is detecting the
 * silent breakage `26` §3.3 warns about — is derived against it.
 *
 * ⚠️ **CHOOSING A MODE IS NOT SWITCHING ON A FORWARD.** The carrier does that,
 * when the owner dials the code on their handset, and nothing in this
 * application can observe it. This service records **what the tenant intends**;
 * that is the honest claim it can make, and the screen says so in words rather
 * than reporting a state it cannot see.
 */
final class CallForwarding
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ActivityService $activity,
        private readonly TenantNumbers $numbers,
    ) {}

    /**
     * How this tenant's calls are handled today.
     *
     * Defaults to {@see CallRoutingMode::TrackingOnly} when no row exists, which
     * is the same answer a freshly created row would give — see the class
     * docblock for why that equivalence is the design rather than a shortcut.
     */
    public function modeFor(): CallRoutingMode
    {
        // ⚠️ AN EXPLICIT NULL CHECK RATHER THAN `?->… ?? …`. The two operators
        // overlap — `??` already tolerates a null base — so PHPStan reports the
        // nullsafe as redundant, and the version that satisfies it
        // (`$row->call_routing_mode ?? …`) reads as though `$row` cannot be null.
        // The next person to "tidy" that away gets a fatal. This says what it
        // means.
        $settings = $this->row();

        return $settings === null
            ? CallRoutingMode::TrackingOnly
            : $settings->call_routing_mode;
    }

    /**
     * The ring timeout the forwarding code carries, in seconds.
     *
     * ⚠️ **IT IS THE CARRIER'S NO-REPLY TIMER, NOT OURS**, which is why it comes
     * out of this table and goes into the dial string rather than into any
     * configuration of our own. `26` §3.1: *"whoever controls the ring timeout
     * controls missed-call detection"* — set it above the carrier's own
     * voicemail trigger and their mailbox answers first, so we see an answered
     * call and text nobody. The column's default of 18 is the migration's, from
     * `29` §19.6, and this reads it rather than restating it.
     */
    public function ringTimeoutSeconds(): int
    {
        $settings = $this->row();

        return $settings === null
            ? SupportSetting::DEFAULT_RING_TIMEOUT_SECONDS
            : $settings->ring_timeout_seconds;
    }

    /**
     * Record how this tenant wants their calls handled.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`, `support:9`.
     */
    public function chooseMode(CallRoutingMode $mode, string $actor): SupportSetting
    {
        // ⚠️ BEFORE THE TRANSACTION AND NOT ASSIGNED TO ANYTHING. It is the
        // fail-closed guard rather than a value this method needs: everything
        // below reaches `support_settings` through the global scope, and a
        // caller with no tenant established would otherwise open a transaction
        // and write a row against nobody. `AuditService::record()` makes the
        // same call for the same reason, one layer down.
        Tenancy::idOrFail();

        return DB::transaction(function () use ($mode, $actor): SupportSetting {
            // ⚠️ `lockForUpdate()` ON THE READ INSIDE THE TRANSACTION. The table
            // carries a unique index on `business_id`, so two saves racing would
            // otherwise be a constraint violation presented to whichever owner
            // clicked second.
            $settings = SupportSetting::query()->lockForUpdate()->first();

            // Read before the row is created, or the "before" side of the audit
            // entry would be the value we are about to write.
            $before = $settings === null
                ? CallRoutingMode::TrackingOnly
                : $settings->call_routing_mode;

            if ($settings === null) {
                $settings = new SupportSetting;
            }

            // forceFill, because `business_id` is guarded on the model and
            // `BelongsToTenant` fills it from context on create — a request body
            // must never be able to name the business a routing mode is written
            // against.
            $settings->forceFill(['call_routing_mode' => $mode])->save();

            // recordChange() rather than record(): `29` §19.3 wants a setting
            // change carrying both sides. An entry holding only the new mode
            // cannot answer whether a business that stopped receiving calls had
            // just moved off `tracking_only`, which is the first question anyone
            // would ask.
            $this->audit->recordChange(
                'call_routing.mode_set',
                $actor,
                before: ['call_routing_mode' => $before->value],
                after: ['call_routing_mode' => $mode->value],
                entity: $settings,
            );

            // ⚠️ THE FEED ENTRY IS NOT THE AUDIT ROW, AND BOTH ARE REQUIRED.
            // `TenantPause` makes the same pairing for the same reason: support
            // can reach this setting on a tenant's behalf, and a business whose
            // calls started or stopped being intercepted with no explanation in
            // their own history is the most confusing state this feature has.
            // No location id — a routing mode is the whole account.
            $this->activity->record(AutopilotActionType::CallRoutingChanged);

            return $settings->refresh();
        });
    }

    /**
     * The codes an owner dials on their handset, aimed at their own number.
     *
     * ⛔ **THESE ARE `26` §3.2's CODES VERBATIM AND THERE IS NO PER-CARRIER
     * TABLE, BECAUSE THE DOCUMENT DOES NOT CARRY ONE** (2914). §3.2 gives four
     * generic GSM strings and one instruction — *"Verify per carrier — behaviour
     * varies, and some carriers and most VoIP/PBX systems need a settings change
     * instead"*. It names no carrier and gives no carrier-specific code, so none
     * is shown. `CLAUDE.md` is explicit that a plausible vendor string is the
     * wrong one and fails silently, and a dial code is the worst case of it: a
     * wrong one is typed into a real handset and either does nothing or forwards
     * every call.
     *
     * ⚠️ **`*67` IS THE DOCUMENT'S BUSY CODE.** The `*71` that reads as the
     * obvious companion to `*61` appears nowhere in `26`, so it is not here
     * either.
     *
     * ⚠️ **THE NUMBER IS DIGITS WITHOUT THE `+`**, which is the form §3.2's own
     * example uses (`**61*19015550182*11*18#`). A `+` inside a GSM supplementary
     * service string is not dialled.
     *
     * ⚠️ **`#` IS `%23` IN THE `tel:` URI AND NOWHERE ELSE.** §3.2: *"Delivered
     * as `tel:` with `#` encoded as `%23`"*. The displayed string keeps its
     * hashes, because that is what an owner reads off the page and types.
     *
     * @return list<array{label: string, code: string, tel: string}> empty when
     *                                                               this tenant
     *                                                               has no number
     */
    public function dialCodes(): array
    {
        $number = $this->numbers->forBusiness(Tenancy::idOrFail());

        if ($number === null) {
            return [];
        }

        $digits = ltrim((string) $number->e164, '+');
        $seconds = $this->ringTimeoutSeconds();

        return array_map(
            fn (array $entry): array => $entry + ['tel' => 'tel:'.str_replace('#', '%23', $entry['code'])],
            [
                [
                    'label' => 'Forward when you do not answer',
                    'code' => '**61*'.$digits.'*11*'.$seconds.'#',
                ],
                [
                    'label' => 'Forward when your line is busy',
                    'code' => '*67*'.$digits.'#',
                ],
                [
                    'label' => 'Undo the no-answer forward',
                    'code' => '##61#',
                ],
                [
                    'label' => 'Undo the busy forward',
                    'code' => '##67#',
                ],
            ],
        );
    }

    /**
     * This tenant's row, or null when they have never chosen.
     *
     * No `business_id` predicate: `SupportSetting` carries `BelongsToTenant`, so
     * the global scope adds one and row-level security enforces it underneath.
     * Writing the filter by hand here would be a third idea of what the tenant
     * is, and `CLAUDE.md` is explicit that RLS catches a *forgotten* filter and
     * never a *wrong* one.
     */
    /**
     * Who picks up first when a call reaches this tenant's number (owner ruling D-6, 2026-10-05): the AI receptionist
     * straight away unless the owner chose to be rung first. No row gives the same answer a new row would — the column's
     * default — exactly as {@see self::modeFor()} does.
     */
    public function liveAnswerFor(): LiveAnswerMode
    {
        $settings = $this->row();

        return $settings === null
            ? LiveAnswerMode::AiFirst
            : $settings->live_answer_mode;
    }

    /**
     * Record who picks up first. Audited with both sides, for {@see self::chooseMode()}'s reason.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`, `support:9`.
     */
    public function chooseLiveAnswer(LiveAnswerMode $mode, string $actor): SupportSetting
    {
        // The fail-closed guard chooseMode() explains: no tenant, no transaction, no row written against nobody.
        Tenancy::idOrFail();

        return DB::transaction(function () use ($mode, $actor): SupportSetting {
            $settings = SupportSetting::query()->lockForUpdate()->first();

            $before = $settings === null
                ? LiveAnswerMode::AiFirst
                : $settings->live_answer_mode;

            if ($settings === null) {
                $settings = new SupportSetting;
            }

            // forceFill: `business_id` is guarded and `BelongsToTenant` fills it from context.
            $settings->forceFill(['live_answer_mode' => $mode])->save();

            $this->audit->recordChange(
                'call_routing.live_answer_set',
                $actor,
                before: ['live_answer_mode' => $before->value],
                after: ['live_answer_mode' => $mode->value],
                entity: $settings,
            );

            return $settings->refresh();
        });
    }

    private function row(): ?SupportSetting
    {
        return SupportSetting::query()->first();
    }
}
