<?php

declare(strict_types=1);

namespace App\Modules\X160\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'document_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
    ];
}
