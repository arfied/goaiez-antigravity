<?php

declare(strict_types=1);

namespace App\Modules\X156\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class IngestRejection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ingest_rejections';

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'signature_verified' => 'boolean',
    ];
}
