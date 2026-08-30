<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    protected $table = 'review_replies';

    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];
}
