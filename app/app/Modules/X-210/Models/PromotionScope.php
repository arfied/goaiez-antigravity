<?php

declare(strict_types=1);

namespace App\Modules\X210\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PromotionScope extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'promotion_scopes';

    protected $guarded = [];
}
