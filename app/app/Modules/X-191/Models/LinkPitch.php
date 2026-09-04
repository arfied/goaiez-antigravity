<?php

declare(strict_types=1);

namespace App\Modules\X191\Models;

use Illuminate\Database\Eloquent\Model;

class LinkPitch extends Model
{
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
