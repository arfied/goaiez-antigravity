<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAnsweredQuestion extends Model
{
    protected $table = 'site_answered_questions';

    protected $guarded = [];

    protected $casts = [
        'answered_at' => 'datetime',
    ];
}
