<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use Illuminate\Database\Eloquent\Model;

class Voicemail extends Model
{
    protected $table = 'voicemails';

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'integer',
    ];
}
