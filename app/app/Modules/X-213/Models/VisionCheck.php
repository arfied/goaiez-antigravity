<?php

declare(strict_types=1);

namespace App\Modules\X213\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class VisionCheck extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'vision_checks';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'defect_flags' => 'array',
    ];
}
