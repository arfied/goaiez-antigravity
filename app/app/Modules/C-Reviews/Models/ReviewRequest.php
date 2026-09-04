<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $platform
 * @property int $rating
 * @property ?string $customer_name
 * @property ?string $review_text
 * @property bool $gbp_suspended
 * @property ?string $status
 */
class ReviewRequest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'review_requests';

    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
        'gbp_suspended' => 'boolean',
    ];
}
