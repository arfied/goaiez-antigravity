<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\DunningOutcome;
use Database\Factories\DunningAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One attempt to recover a failed payment (T137 SL-11, decisions 2108, 2133).
 *
 * ⚠️ **THIS TABLE IS THE SUSPENSION RULE, NOT A LOG OF IT.** "N declines and the
 * tenant loses the product" is counted from these rows, so an attempt that ran
 * and was not written silently extends somebody's grace period, and one written
 * twice shortens it. That is why `(business_id, sequence, attempt)` is unique:
 * a retried job lands on the row it already wrote rather than adding a second.
 *
 * ⚠️ **AUTHORIZE.NET HAS NO NATIVE DUNNING** — decision 2108 and T137's own
 * roster both say so. Verified against the vendor's live documentation
 * (2026-08-11): a decline *suspends*, the vendor does not retry, and it
 * *terminates* if nobody acts before the next run date. Every row here is work
 * a Stripe integration would not have had to do.
 *
 * APPEND-ONLY. An attempt that has happened has happened, and the outcome is
 * known when the row is written — unlike the two event tables, there is no
 * placeholder-then-answer pattern here, so there is nothing legitimate to
 * update.
 *
 * @property int $id
 * @property int $business_id
 * @property int $sequence
 * @property int $attempt
 * @property DunningOutcome $outcome
 * @property ?string $reason_code
 * @property Carbon $attempted_at
 * @property ?Carbon $next_attempt_at
 */
final class DunningAttempt extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<DunningAttemptFactory> */
    use HasFactory;

    public const null CREATED_AT = null;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'dunning_attempts is append-only. Editing an attempt changes how many '
                .'failures a tenant has had, which is the number a suspension is '
                .'decided from.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'dunning_attempts is append-only. Deleting an attempt gives a tenant '
                .'back a retry they have already used, silently.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DunningOutcome::class,
            'sequence' => 'integer',
            'attempt' => 'integer',
            'attempted_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }
}
