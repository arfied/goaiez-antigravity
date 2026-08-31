<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\OutreachChannel;
use App\Services\Messaging\SendingHealth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One tenant's sending outcomes for one hour on one channel — T137 §3.2.
 *
 * See the creating migration for why this exists beside `number_health_daily`,
 * why the bucket is hourly and UTC, and why an opt-out is not a complaint.
 *
 * ⚠️ **WRITTEN ONLY BY {@see SendingHealth}, AND NEVER WITH `save()`.** Every
 * write is an atomic upsert-and-increment, because two queue workers finishing a
 * send in the same hour will otherwise read-modify-write over each other and the
 * counters come out **low**. A rate that reads low is one that does not trip,
 * which is the wrong direction for a containment to fail in.
 *
 * @property-read int $id
 * @property int $business_id
 * @property Carbon $window_start
 * @property OutreachChannel $channel
 * @property int $sent
 * @property int $delivered
 * @property int $failed
 * @property int $opted_out
 * @property int $complaints
 */
final class SendingHealthWindow extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'window_start',
        'channel',
        'sent',
        'delivered',
        'failed',
        'opted_out',
        'complaints',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_start' => 'immutable_datetime',
            'channel' => OutreachChannel::class,
        ];
    }
}
