<?php

declare(strict_types=1);

namespace App\Modules\X01\Exceptions;

use RuntimeException;

final class LeadRatingOutOfRangeRefused extends RuntimeException
{
    public const REFUSAL_CODE = 'LEAD_RATING_OUT_OF_RANGE';

    public static function forRating(int $rating): self
    {
        return new self("Lead rating out of range: {$rating}");
    }
}
