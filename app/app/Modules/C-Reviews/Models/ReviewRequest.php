<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewRequest extends Model
{
    protected $table = 'review_requests';

    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
        'gbp_suspended' => 'boolean',
    ];
}
