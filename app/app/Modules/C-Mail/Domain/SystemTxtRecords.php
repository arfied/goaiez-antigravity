<?php

declare(strict_types=1);

namespace App\Modules\CMail\Domain;

final class SystemTxtRecords implements TxtRecords
{
    public function txt(string $name): ?array
    {
        if (! function_exists('dns_get_record')) {
            return null;
        }

        $records = @dns_get_record($name, DNS_TXT);

        if ($records === false) {
            return null;
        }

        $out = [];
        foreach ($records as $record) {
            // ⚠️ A TXT string longer than 255 bytes arrives split; PHP exposes the
            // joined value as `txt` and the parts as `entries`. A DKIM key is
            // routinely long enough for this to matter.
            if (isset($record['txt']) && is_string($record['txt'])) {
                $out[] = $record['txt'];
            }
        }

        return $out;
    }
}
