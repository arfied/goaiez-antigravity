<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GatewayEventOutcome;
use App\Services\Billing\StripeWebhooks;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One Stripe event this application has already accepted.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there:
 * an event arrives before any tenant is resolved, and some of them resolve to no
 * tenant at all ({@see GatewayEventOutcome::Unlinked}).
 *
 * ⚠️ **THE ROW IS WRITTEN TWICE AND ONLY THE SECOND WRITE IS PERMITTED.**
 * {@see StripeWebhooks::process()} inserts the claim
 * before doing the work — the unique index on `stripe_event_id` is what makes a
 * concurrent redelivery lose the race — and then writes the real outcome onto
 * it, both inside one transaction. So `outcome` is the one column that may
 * change, exactly once, and every other column is fixed at the claim.
 *
 * ⚠️ **AND NOTHING MAY BE DELETED, WHICH IS THE MECHANICAL HALF.** This is not
 * a compliance record like `opt_outs`; it is the thing that makes a redelivery a
 * no-op. Deleting a row re-arms every handler for an event Stripe may still
 * redeliver — its retries run for three days — and editing the event id or the
 * type changes what we claim to have done with money that has already moved.
 *
 * @property-read int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property GatewayEventOutcome $outcome
 * @property CarbonImmutable $received_at
 */
final class StripeEvent extends Model
{
    public const null UPDATED_AT = null;

    public const null CREATED_AT = null;

    protected $table = 'stripe_events';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(function (self $event): void {
            $changed = array_keys($event->getDirty());

            // `outcome` alone, and only it. The claim insert writes a
            // placeholder because it has not done the work yet; this is that
            // work's answer. Anything else changing means somebody is rewriting
            // which event this row is about.
            if ($changed !== ['outcome']) {
                throw new LogicException(
                    'stripe_events: only `outcome` may change, and only once — the claim '
                    .'row records which event was seen, and rewriting that changes what '
                    .'we claim to have done with a charge. Changed: '.implode(', ', $changed).'.'
                );
            }
        });

        self::deleting(function (): never {
            throw new LogicException(
                'stripe_events is append-only. Deleting a row re-arms every handler for '
                .'an event Stripe may still redeliver — its retries run for three days.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => GatewayEventOutcome::class,
            'received_at' => 'immutable_datetime',
        ];
    }
}
