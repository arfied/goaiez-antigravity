<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\NumberState;
use App\Models\PhoneNumber;
use App\Support\Tenancy;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which of our numbers a text goes out on — row 4 slice 6 phase 1.
 *
 * ⚠️ **THIS IS THE SEAM AND IT IS NOT THE ENGINE, AND SAYING SO IS THE POINT OF
 * THE FILE.** There is exactly one number in this platform's inventory today, so
 * every call below returns it. Health-weighted rotation, warmup caps, sticky
 * senders and the per-day ceilings are doc 51 §3–§6 and are **phase 2 and phase
 * 4** — deliberately not built, and deliberately not hinted at here. Slices 1
 * and 3 both proved that a seam is worth shipping on its own; what this buys is
 * that the day a second number exists, the change is inside this class rather
 * than at every send site.
 *
 * ⚠️ **AND THE ABSENCE OF ROTATION IS NOT A GAP TO CLOSE OPPORTUNISTICALLY.**
 * `BUILD-PLAN` §2.10.3 quotes doc 51's own principle first: *"a sick number
 * rests and recovers"*, never replaced — replacing numbers to keep sending is
 * snowshoeing and `29` §2 rule 12 forbids it. A weighting function written
 * before the health signals exist would be a rotation policy invented from
 * nothing, in the one part of this product where the regulator's word for the
 * mistake already exists.
 *
 * ## What it does decide
 *
 * **The tenant's own number first, the shared pool second.** A tenant with its
 * own Lane B number sends from it; everybody else sends from the platform's
 * shared Lane A number. That is doc 51 §10's `NULL = system/shared pool` read as
 * a precedence rather than as a fallback, and it is the only ordering rule in
 * here — `id` breaks the tie so the choice is stable rather than
 * database-order-dependent.
 *
 * **Never another tenant's number.** The `phone_numbers` policy is `USING
 * (true)` — the table has platform-scoped readers, so RLS cannot carry the
 * predicate (562's posture, argued in the creating migration). The predicate is
 * therefore written here, explicitly, and it is the only thing between one
 * tenant and another tenant's registered sender.
 *
 * **Nothing that may not send.** {@see SendingNumber} is unconstructable from a
 * row outside {@see NumberState::maySend()}, so I38 holds even if the query
 * below is wrong — the state filter is an optimisation of a guarantee the type
 * already makes, not the guarantee itself.
 */
final class NumberSelector
{
    /**
     * The number this send goes out on, or null when none may.
     *
     * ⚠️ **NULL IS AMBIGUOUS ON ITS OWN AND {@see self::hasNumbers()} IS WHAT
     * DISAMBIGUATES IT.** Null means either *"this platform has no inventory
     * yet"* — the bootstrap case, where `InfobipClient` falls back to the
     * configured sender — or *"there are numbers and every one of them is
     * quarantined, retired or still registering"*, which must refuse the send
     * outright. Collapsing the two would make a quarantine silently fall back to
     * the configured number, which is the exact thing a quarantine exists to
     * stop.
     */
    public function forSending(): ?SendingNumber
    {
        $sendable = self::values(static fn (NumberState $state): bool => $state->maySend());

        $number = $this->candidates()
            ->whereIn('state', $sendable)
            // The tenant's own before the shared pool. `business_id IS NULL`
            // sorts false (0) before true (1) in Postgres, so a tenant-owned row
            // wins without a CASE expression.
            ->orderByRaw('business_id IS NULL')
            ->orderBy('id')
            ->first();

        return $number === null ? null : SendingNumber::from($number);
    }

    /**
     * The number a **carrier-mandated compliance reply** goes out on — a STOP
     * confirmation or a HELP answer, and nothing else.
     *
     * ⛔ **IT DOES NOT CONSULT THE HEALTH STATE, AND THAT IS THE ENTIRE REASON
     * IT EXISTS** (AG6 fix wave). {@see self::forSending()} returns null when
     * every number is resting, which is exactly right for outreach and exactly
     * wrong here: the first auto-quarantine of the shared Lane A number would
     * otherwise have silenced every STOP confirmation and every HELP answer on
     * the platform. 2099 and 2125 make both replies unconditional, and
     * {@see ComplianceReplies}'s own docblock enumerates the only two things it
     * defers to — `sms.enabled` and `messaging.global_halt` — arguing each is
     * *"an operator's decision to make knowingly"*. **An automatic quarantine is
     * neither.** {@see NumberState::mayAnswerCompliance()} holds the admitted
     * set and the argument for its edges.
     *
     * ⚠️ **IT RETURNS THE E.164 AND NOT A {@see SendingNumber}, DELIBERATELY.**
     * That type means *this platform may originate traffic from this number*,
     * and a resting number may not — minting one for a quarantined row would
     * make I38 a sentence rather than a guarantee, and the next caller to hold
     * one would be right to treat it as an outreach licence. A compliance reply
     * writes no `outreach_messages` row (`SendSettlement` says so), so the row id
     * that type also carries has nowhere to go on this path anyway.
     *
     * ⚠️ **A HEALTHY NUMBER STILL WINS WHEN THERE IS ONE.** The ordering puts
     * every sendable state ahead of every resting one, so this only ever reaches
     * for a quarantined number when there is nothing better — the containment is
     * still preferred, it is just no longer allowed to produce silence.
     */
    public function forComplianceReply(): ?string
    {
        $admitted = self::values(static fn (NumberState $state): bool => $state->mayAnswerCompliance());
        $sendable = self::values(static fn (NumberState $state): bool => $state->maySend());

        $e164 = $this->candidates()
            ->whereIn('state', $admitted)
            // Health first, ownership second. `forSending()` orders the other
            // way round because for outreach every candidate is already healthy
            // and the only question left is whose it is; here the first question
            // is whether the reply will actually arrive.
            ->orderByRaw(
                'case when state in ('.implode(', ', array_fill(0, count($sendable), '?')).') then 0 else 1 end',
                $sendable,
            )
            ->orderByRaw('business_id IS NULL')
            ->orderBy('id')
            ->value('e164');

        return is_string($e164) ? $e164 : null;
    }

    /**
     * Whether this send has any number recorded at all, sendable or not.
     *
     * The precise complement of {@see self::forSending()} rather than a count of
     * the whole table: a tenant whose own number is quarantined must not fall
     * back to the configured sender just because some *other* tenant has a
     * healthy one.
     */
    public function hasNumbers(): bool
    {
        return $this->candidates()->exists();
    }

    /**
     * The stored values of every state answering `$admits`.
     *
     * ⚠️ **DERIVED FROM THE ENUM, NEVER LISTED.** A hand-written list of state
     * strings is a second copy of {@see NumberState::maySend()} that drifts the
     * first time a state is added — and the drift is silent, because a missing
     * value simply narrows a `whereIn` rather than failing anything.
     *
     * @param  callable(NumberState): bool  $admits
     * @return list<string>
     */
    private static function values(callable $admits): array
    {
        return array_values(array_map(
            static fn (NumberState $state): string => $state->value,
            array_filter(NumberState::cases(), $admits),
        ));
    }

    /**
     * Every number this send could ever have gone out on.
     *
     * @return Builder<PhoneNumber>
     */
    private function candidates(): Builder
    {
        $tenant = Tenancy::id();

        return PhoneNumber::query()->where(function (BuilderContract $query) use ($tenant): void {
            // The shared Lane A pool, which belongs to nobody and is available
            // to everybody — including a request with no tenant resolved at all.
            $query->whereNull('business_id');

            if ($tenant !== null) {
                $query->orWhere('business_id', $tenant);
            }
        });
    }
}
