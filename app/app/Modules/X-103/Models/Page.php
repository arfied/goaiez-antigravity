<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $table = 'pages';

    protected $guarded = [];

    protected $casts = [
        'is_tenant_edited' => 'boolean',
        'is_published' => 'boolean',
        'draft_blocks' => 'array',
        'draft_meta' => 'array',
        'seo_title' => 'string',
        'seo_description' => 'string',
    ];
}
