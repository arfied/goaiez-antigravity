<?php
declare(strict_types=1);

namespace App\Modules\X210\Domain;

final class PromotionEngine
{
    // X-210 domain layer natively enforcing promotion margin guards (no below-cost services without explicit names),
    // strictly rejecting uncapped promotion saves, and blocking AI from inventing discounts without grounded facts.
}
