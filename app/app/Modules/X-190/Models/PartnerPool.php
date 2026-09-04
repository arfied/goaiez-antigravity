<?php

declare(strict_types=1);

namespace App\Modules\X190\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerPool extends Model
{
    protected $table = 'partner_pool';

    protected $guarded = [];

    protected $casts = [
        'research_data' => 'array',
        'fetch_count' => 'integer',
        'is_declined' => 'boolean',
    ];
}
