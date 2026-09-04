<?php

declare(strict_types=1);

namespace App\Modules\X148\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'knowledge_chunks';

    protected $guarded = [];

    protected $casts = [
        'embedding_vector' => 'array',
    ];
}
