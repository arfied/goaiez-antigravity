<?php

namespace App\Modules\X198\Domain;

/**
 * The request was never sent; nothing is known about the money.
 * (X-198, MONEY-62 (R245))
 */
final class GatewayNotConfiguredException extends \RuntimeException {}
