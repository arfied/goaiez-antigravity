<?php

declare(strict_types=1);

namespace App\Modules\X180\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ContentPack extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'content_packs';

    protected $guarded = [];

    protected $casts = [
        'assets_count' => 'integer',
        'assets_manifest' => 'array',
    ];
}
