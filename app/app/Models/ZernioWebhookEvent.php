<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Zernio webhook event we have already accepted.
 *
 * Platform-scoped receipt store — see the creating migration. Unique on
 * `event_id` so a redelivery is a no-op rather than a second ingest.
 *
 * @property-read int $id
 * @property string $event_id
 * @property string $event_type
 * @property Carbon $received_at
 */
final class ZernioWebhookEvent extends Model
{
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
            'received_at' => 'datetime',
        ];
    }
}
