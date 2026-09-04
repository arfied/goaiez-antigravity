<?php

declare(strict_types=1);

namespace App\Modules\X114\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'media_assets';

    protected $guarded = [];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'is_client_upload' => 'boolean',
        'is_generated' => 'boolean',
        'tags' => 'array',
    ];
}
