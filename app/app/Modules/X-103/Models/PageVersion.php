<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class PageVersion extends Model
{
    protected $table = 'page_versions';

    protected $guarded = [];

    protected $casts = [
        'content_blocks' => 'array',
        'pixel_installed' => 'boolean',
    ];
}
