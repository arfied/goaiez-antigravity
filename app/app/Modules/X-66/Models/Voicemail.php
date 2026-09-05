<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voicemail extends Model
{
    use HasFactory;
    protected $table = 'voicemails';

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'integer',
    ];
}
