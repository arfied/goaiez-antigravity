<?php

declare(strict_types=1);

namespace App\Modules\X176\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CrawlerVisit extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'crawler_visits';

    protected $guarded = [];

    protected $casts = [
        'visited_at' => 'datetime',
    ];
}
