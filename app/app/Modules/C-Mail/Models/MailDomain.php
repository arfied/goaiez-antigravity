<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use Illuminate\Database\Eloquent\Model;

class MailDomain extends Model
{
    protected $table = 'mail_domains';

    protected $guarded = [];

    protected $casts = [
        'is_marketing_paused' => 'boolean',
        'complaint_rate' => 'float',
    ];
}
