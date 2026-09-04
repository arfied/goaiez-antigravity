<?php

declare(strict_types=1);

namespace App\Modules\X124\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AssistantRecommendation extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'assistant_recommendations';

    protected $guarded = [];
}
