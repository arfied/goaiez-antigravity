<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ErrorBucket;
use App\Services\Sms\NumberHealthService;
use Illuminate\Database\Eloquent\Model;

/**
 * One Infobip DLR error name, and which pile it falls into — doc `51` §4.2, §10.
 *
 * NOT TENANT-OWNED, NO RLS — see the creating migration. Read only by
 * {@see NumberHealthService}; a chokepoint lint in `MessagingTest` confines
 * this model to that one file, which is I45's "one scorer" made mechanical.
 *
 * @property-read int $id
 * @property string $error_name
 * @property ErrorBucket $bucket
 */
final class DlrErrorBucket extends Model
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
            'bucket' => ErrorBucket::class,
        ];
    }
}
