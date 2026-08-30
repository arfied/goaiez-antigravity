<?php

declare(strict_types=1);

namespace App\Modules\X132\Models;

use Illuminate\Database\Eloquent\Model;

class PersonLink extends Model
{
    protected $table = 'person_links';

    protected $guarded = [];

    protected $casts = [
        'confidence_score' => 'float',
    ];
}
