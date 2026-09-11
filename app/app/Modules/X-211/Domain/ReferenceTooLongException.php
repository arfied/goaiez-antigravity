<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

/**
 * A payment reference longer than the column can hold is refused before the write.
 */
final class ReferenceTooLongException extends \DomainException {}
