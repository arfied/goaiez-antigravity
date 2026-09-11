<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use Illuminate\Database\Eloquent\Model;

class ArCollectionsPackage extends Model
{
    protected $table = 'ar_collections_packages';

    protected $guarded = [];

    protected $casts = [
        'contents' => 'array',
        'transmitted_at' => 'datetime',
    ];
}
