<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StateMessagingRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One state's mini-TCPA rules (`29` §2 rule 11, `24` §3.4).
 *
 * NOT TENANT-OWNED, on the `TenancyTest` scope allowlist. The same family
 * as `LegalDocument` (417–420): a statute is not a tenant's data, and every
 * business in a state is bound by the same row.
 *
 * @property-read int $id
 * @property string $state
 * @property string $quiet_hours_start
 * @property string $quiet_hours_end
 * @property bool $requires_written_consent
 * @property string $citation
 * @property ?Carbon $effective_from
 * @property ?string $notes
 */
final class StateMessagingRule extends Model
{
    /** @use HasFactory<StateMessagingRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_written_consent' => 'boolean',
            'effective_from' => 'immutable_date',
        ];
    }

    /**
     * Whether this state forbids messaging at the given local moment.
     *
     * ⚠️ THE WINDOW WRAPS MIDNIGHT AND THE COMPARISON HAS TO SAY SO. Every one
     * of these statutes forbids an evening-to-morning band — 21:00 to 08:00 —
     * so `start` is greater than `end` on every real row, and the naive
     * `$now >= $start && $now < $end` is false at every hour of the day. A rule
     * that permits everything looks exactly like a rule that is working, which
     * is why this is a method with a test rather than an inline comparison.
     *
     * ⚠️ THE CALLER OWNS THE TIMEZONE. `24` §3.4 puts quiet hours "in the
     * location's timezone", and this method takes an already-localised moment
     * rather than converting one, because a model silently applying a timezone
     * is a model that applies the *server's* timezone when the caller forgot.
     * `StateMessagingRules::prohibitsNow()` is what resolves it.
     */
    public function prohibitsAt(CarbonImmutable $localTime): bool
    {
        return self::prohibits($this->quiet_hours_start, $this->quiet_hours_end, $localTime);
    }

    /**
     * The same question with the window passed in rather than read off a row.
     *
     * ⚠️ **STATIC BECAUSE THERE IS A SECOND WINDOW AND IT HAS NO ROW** (1609).
     * The platform base quiet-hours window applies wherever a state has filed no
     * statute, which is most states — and it is the same evening-to-morning band
     * with the same midnight wrap. Two spellings of a wrapping comparison is the
     * hazard `ConsentService::decide()`/`permit()` names out loud: the second
     * copy is the one that quietly stops matching, and here that would mean a
     * window that permits at exactly the hours it was written to close. So the
     * arithmetic lives once and both callers reach it.
     */
    public static function prohibits(string $start, string $end, CarbonImmutable $localTime): bool
    {
        $at = $localTime->format('H:i:s');
        $start = self::normaliseTime($start);
        $end = self::normaliseTime($end);

        // The ordinary case: an evening-to-morning band crossing midnight.
        if ($start > $end) {
            return $at >= $start || $at < $end;
        }

        // A band inside one day. No real statute is written this way today, but
        // the column permits it and a comparison that only handled the wrapping
        // case would answer the other one wrongly rather than refusing it.
        return $at >= $start && $at < $end;
    }

    /**
     * Postgres returns a `time` column as `HH:MM:SS`, and a factory, a seeder or
     * a registry key may hand over `HH:MM`. String comparison of the two forms
     * is wrong at the boundary — `'21:00' >= '21:00:00'` is false — so both are
     * widened before anything is compared.
     */
    private static function normaliseTime(string $value): string
    {
        return substr($value.':00:00', 0, 8);
    }
}
