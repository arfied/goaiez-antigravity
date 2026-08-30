<?php

declare(strict_types=1);

namespace App\Modules\X148\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $table = 'knowledge_chunks';

    protected $guarded = [];

    protected $casts = [
        'embedding_vector' => 'array',
    ];
}
