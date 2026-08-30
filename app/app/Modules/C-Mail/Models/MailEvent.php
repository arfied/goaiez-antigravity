<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use Illuminate\Database\Eloquent\Model;

class MailEvent extends Model
{
    protected $table = 'mail_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
