<?php

declare(strict_types=1);

namespace App\Modules\X148\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class RetrievalCache extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'retrieval_cache';

    protected $guarded = [];

    protected $casts = [
        'result_chunk_ids' => 'array',
    ];
}
