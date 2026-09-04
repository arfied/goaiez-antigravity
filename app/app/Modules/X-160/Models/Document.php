<?php

declare(strict_types=1);

namespace App\Modules\X160\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Document extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'documents';

    protected $guarded = [];
}
