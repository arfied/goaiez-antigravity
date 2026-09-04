<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $domain
 * @property bool $is_marketing_paused
 * @property float $complaint_rate
 */
class MailDomain extends Model
{
    protected $table = 'mail_domains';

    protected $guarded = [];

    protected $casts = [
        'is_marketing_paused' => 'boolean',
        'complaint_rate' => 'float',
    ];
}
