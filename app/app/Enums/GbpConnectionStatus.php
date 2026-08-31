<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a location's Google Business connection has got to.
 *
 * ⚠️ **NOT {@see ConnectionStatus}, AND THE DIFFERENCE IS `Pending`.** That enum
 * is DATA-MODEL §5.1's `connection_status` for `oauth_connections`, whose four
 * cases all describe a grant we already hold — active, expired, revoked, error.
 * A connection here exists *before* there is anything to describe: the row is
 * written when the owner leaves for the provider's consent screen, so that a
 * flow abandoned halfway is a state we can see rather than an absence we have to
 * infer. Folding that into `Active|Expired|Revoked|Error` would either invent a
 * fifth case on a schema-derived enum another table reads, or record a grant
 * nobody has made yet.
 */
enum GbpConnectionStatus: string
{
    /**
     * The owner started the flow and has not come back.
     *
     * Not an error and not a failure — the ordinary state of somebody reading
     * Google's consent screen. It carries no `account_ref`, which is why the
     * database refuses the pairing that would matter (`gbp_connections_
     * connected_rows_carry_an_account`).
     */
    case Pending = 'pending';

    case Connected = 'connected';

    /**
     * The provider says this connection no longer works.
     *
     * ⚠️ **Only ever written from an answer that distinguishes a dead connection
     * from our own billing** (532). Zernio documents `permission_error` — *"valid
     * key but feature requires plan upgrade"* — on the same 403 a revoked tenant
     * grant produces. Writing this case on every 403 would send every owner to
     * re-authorise a connection that is fine, while the real fix is an invoice
     * nobody is reading.
     */
    case Disconnected = 'disconnected';

    /**
     * What the owner reads on their own screen.
     *
     * Outcome language, `22`: it names the state of the thing they control, not
     * the state of a row.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not finished',
            self::Connected => 'Connected',
            self::Disconnected => 'Needs reconnecting',
        };
    }
}
