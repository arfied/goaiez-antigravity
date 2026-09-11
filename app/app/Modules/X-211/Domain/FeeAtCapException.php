<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

/**
 * G1-71: a fee is applied only inside the agreement's term — the cap is a ceiling on the invoice,
 * so once the receivable already carries it a further fee is refused before any write.
 * P-193: the percent and the cap are the tenant's row
 */
final class FeeAtCapException extends \DomainException {}
