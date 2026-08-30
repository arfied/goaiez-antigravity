<?php

declare(strict_types=1);

namespace App\Modules\X179\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateMatch extends Model
{
    protected $table = 'template_matches';

    protected $guarded = [];

    protected $casts = [
        'match_score' => 'float',
    ];
}
