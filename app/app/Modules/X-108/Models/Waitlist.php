<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use Illuminate\Database\Eloquent\Model;

class Waitlist extends Model
{
    protected $table = 'waitlists';

    protected $guarded = [];

    protected $casts = [
        'preferred_date' => 'date',
        'is_member' => 'boolean',
    ];
}
