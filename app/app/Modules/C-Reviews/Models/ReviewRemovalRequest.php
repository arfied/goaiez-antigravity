<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $review_request_id
 * @property ?string $google_review_id
 * @property ?string $tos_ground
 * @property ?string $prepared_body
 * @property ?Carbon $prepared_at
 * @property ?int $confirmed_by_user_id
 * @property ?Carbon $confirmed_at
 * @property string $status
 */
class ReviewRemovalRequest extends Model
{
    protected $table = 'review_removal_requests';

    protected $guarded = [];

    protected $casts = [
        'prepared_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];
}
