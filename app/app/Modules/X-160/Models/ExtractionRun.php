<?php

declare(strict_types=1);

namespace App\Modules\X160\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ExtractionRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'extraction_runs';

    protected $guarded = [];

    protected $casts = [
        'extracted_facts_count' => 'integer',
    ];
}
