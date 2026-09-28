<?php

declare(strict_types=1);

namespace App\Modules\CMail\Domain;

/**
 * The fail-closed default: no lookup was possible, so nothing is verified.
 *
 * ⚠️ Reached only by a hand-built `new EmailDnsCheckAction`, which is what the
 * existing test fixtures do. 486's rule — the safe-looking default is usually the
 * permissive one — is why this answers `null` rather than `[]`.
 */
final class NoTxtRecords implements TxtRecords
{
    public function txt(string $name): ?array
    {
        return null;
    }
}
