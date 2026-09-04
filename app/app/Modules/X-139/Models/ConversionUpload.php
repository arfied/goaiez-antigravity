<?php

declare(strict_types=1);

namespace App\Modules\X139\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ConversionUpload extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'conversion_uploads';

    protected $guarded = [];

    protected $casts = [
        'conversion_value_cents' => 'integer',
    ];
}
