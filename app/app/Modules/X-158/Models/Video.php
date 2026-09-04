<?php

declare(strict_types=1);

namespace App\Modules\X158\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Video extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'videos';

    protected $guarded = [];

    protected $casts = [
        'demo_number' => 'integer',
        'is_rendered' => 'boolean',
    ];

    public function views()
    {
        return $this->hasMany(VideoView::class, 'video_id');
    }
}
