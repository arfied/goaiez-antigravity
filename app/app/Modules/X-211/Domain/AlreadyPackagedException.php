<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

/**
 * An invoice is packaged for collections once. The preview already drops a packaged invoice from its
 * candidate list, so a second package can only be a double press or a stale page — it is refused
 * before any write rather than recorded as a second handover.
 */
final class AlreadyPackagedException extends \DomainException {}
