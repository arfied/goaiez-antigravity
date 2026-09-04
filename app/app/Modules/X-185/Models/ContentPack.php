<?php

declare(strict_types=1);

namespace App\Modules\X185\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPack extends Model
{
    protected $table = 'content_packs';

    protected $guarded = [];

    protected $casts = [
        'fleet_sample_size' => 'integer',
        'is_promoted' => 'boolean',
    ];
}
