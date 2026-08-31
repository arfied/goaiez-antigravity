<?php

declare(strict_types=1);

namespace App\Modules\X191\Models;

use Illuminate\Database\Eloquent\Model;

class LinkTarget extends Model
{
    protected $table = 'link_targets';

    protected $guarded = [];

    protected $casts = [
        'is_pbn' => 'boolean',
        'domain_authority' => 'integer',
    ];
}
