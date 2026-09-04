<?php

declare(strict_types=1);

namespace App\Modules\X204\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ImportAttestation extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'import_attestations';

    protected $guarded = [];

    protected $casts = [
        'contacts_count' => 'integer',
        'attested_at' => 'datetime',
    ];
}
