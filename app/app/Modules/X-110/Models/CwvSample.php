<?php

declare(strict_types=1);

namespace App\Modules\X110\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CwvSample extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'cwv_samples';

    protected $guarded = [];

    protected $casts = [
        'lcp_ms' => 'integer',
        'fid_ms' => 'integer',
        'cls_score' => 'float',
    ];
}
