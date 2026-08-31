<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The health of an OAuth connection (DATA-MODEL §5.1 `connection_status`).
 *
 * Expired and Revoked both mean "cannot act", but they demand different fixes:
 * an expired token might refresh transparently; a revoked one needs the owner
 * to reconnect.
 */
enum ConnectionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Error = 'error';
}
