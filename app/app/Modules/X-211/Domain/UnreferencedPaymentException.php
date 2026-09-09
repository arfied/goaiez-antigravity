<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

/**
 * G1-74, N-033: a logged offline payment carries a reference or a photo, or it is refused
 */
final class UnreferencedPaymentException extends \DomainException {}
