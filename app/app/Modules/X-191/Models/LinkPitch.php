<?php

declare(strict_types=1);

namespace App\Modules\X191\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LinkPitch extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'link_pitches';

    protected $guarded = [];

    protected $casts = [
        'follow_up_count' => 'integer',
        'is_sent' => 'boolean',
    ];

    public function target()
    {
        return $this->belongsTo(LinkTarget::class, 'target_id');
    }
}
