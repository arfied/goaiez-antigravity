<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'review_replies';

    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];
}
