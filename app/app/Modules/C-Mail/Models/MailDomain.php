<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $domain
 * @property bool $is_marketing_paused
 * @property float $complaint_rate
 */
class MailDomain extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'mail_domains';

    protected $guarded = [];

    protected $casts = [
        'is_marketing_paused' => 'boolean',
        'complaint_rate' => 'float',
    ];
}
