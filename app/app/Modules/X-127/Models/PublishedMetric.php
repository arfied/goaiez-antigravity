<?php

declare(strict_types=1);

namespace App\Modules\X127\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PublishedMetric extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'published_metrics';

    protected $guarded = [];
}
