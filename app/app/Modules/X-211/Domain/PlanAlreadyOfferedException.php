<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

/**
 * An invoice carries one plan. The builder already drops a planned invoice from its candidate list,
 * so a second offer can only be a double press or a stale page — it is refused before any write
 * rather than recorded as a second agreement.
 */
final class PlanAlreadyOfferedException extends \DomainException {}
