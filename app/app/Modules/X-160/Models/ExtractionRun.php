<?php

declare(strict_types=1);

namespace App\Modules\X160\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractionRun extends Model
{
    protected $table = 'extraction_runs';

    protected $guarded = [];

    protected $casts = [
        'extracted_facts_count' => 'integer',
    ];
}
