<?php

declare(strict_types=1);

namespace App\Modules\X219\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AiModuleAssignment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ai_module_assignments';

    protected $guarded = [];
}
