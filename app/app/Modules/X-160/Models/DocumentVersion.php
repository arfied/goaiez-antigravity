<?php

declare(strict_types=1);

namespace App\Modules\X160\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $table = 'document_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
    ];
}
