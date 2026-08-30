<?php

declare(strict_types=1);

namespace App\Modules\X180\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPack extends Model
{
    protected $table = 'content_packs';

    protected $guarded = [];

    protected $casts = [
        'assets_count' => 'integer',
        'assets_manifest' => 'array',
    ];
}
