<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GatewayEventOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One Authorize.Net webhook this application has already accepted.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist for `StripeEvent`'s
 * reason: an event arrives before any tenant is resolved, and some of them
 * resolve to no tenant at all.
 *
 * ⚠️ **THE ROW IS WRITTEN TWICE AND ONLY THE SECOND WRITE IS PERMITTED.**
 * The webhook service inserts the claim before doing the work — the unique index
 * on `notification_id` is what makes a concurrent redelivery lose the race — and
 * then writes the real outcome onto it, both inside one transaction. So
 * `outcome` is the one column that may change, exactly once, and every other
 * column is fixed at the claim.
 *
 * ⚠️ **AND NOTHING MAY BE DELETED, WHICH IS THE MECHANICAL HALF — AND IT BINDS
 * HARDER HERE THAN ON THE STRIPE TABLE.** Stripe documents a three-day retry
 * window, so a deleted claim row there re-arms a handler for a bounded period.
 * **Authorize.Net publishes no retry schedule at all**, so there is no window to
 * reason about: the row is the only thing that can answer "have we done this",
 * and the answer has to survive.
 *
 * @property-read int $id
 * @property string $notification_id
 * @property string $type
 * @property GatewayEventOutcome $outcome
 * @property CarbonImmutable $received_at
 */
final class AuthorizeNetEvent extends Model
{
    public const null UPDATED_AT = null;

    public const null CREATED_AT = null;

    protected $table = 'authorize_net_events';

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
                    'authorize_net_events: only `outcome` may change, and only once — the '
                    .'claim row records which notification was seen, and rewriting that '
                    .'changes what we claim to have done with a charge. Changed: '
                    .implode(', ', $changed).'.'
                );
            }
        });

        self::deleting(function (): never {
            throw new LogicException(
                'authorize_net_events is append-only. Deleting a row re-arms every handler '
                .'for a notification the vendor may redeliver — and unlike Stripe, '
                .'Authorize.Net publishes no retry window, so there is no period after '
                .'which that stops being true.'
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
