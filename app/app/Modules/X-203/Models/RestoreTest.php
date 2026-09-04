<?php

declare(strict_types=1);

namespace App\Modules\X203\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class RestoreTest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'restore_tests';

    protected $guarded = [];

    protected $casts = [
        'expected_row_count' => 'integer',
        'restored_row_count' => 'integer',
        'tested_at' => 'datetime',
    ];
}
