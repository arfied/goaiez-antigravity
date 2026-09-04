<?php

declare(strict_types=1);

namespace App\Modules\X140\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TopicSource extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'topic_sources';

    protected $guarded = [];
}
