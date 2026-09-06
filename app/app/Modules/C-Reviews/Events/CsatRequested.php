<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class CsatRequested
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly int $ticketId,
        public readonly ?int $personId
    ) {}
}
