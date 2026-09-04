<?php

declare(strict_types=1);

namespace App\Modules\X124\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AssistantUnsupported extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'assistant_unsupported';

    protected $guarded = [];
}
