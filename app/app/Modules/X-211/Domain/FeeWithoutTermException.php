<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

/**
 * G1-71: a fee with no matching term in the agreement is refused before any write
 * P-193: the percent and the cap are the tenant's row
 */
final class FeeWithoutTermException extends \DomainException {}
