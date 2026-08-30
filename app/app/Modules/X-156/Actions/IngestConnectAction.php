<?php

declare(strict_types=1);

namespace App\Modules\X156\Actions;

use App\Modules\X156\Models\IngestSource;

final class IngestConnectAction
{
    public function connect(
        int $businessId,
        string $sourceType,
        string $sourceName,
        ?string $secretKey = null
    ): IngestSource {
        return IngestSource::create([
            'business_id' => $businessId,
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'secret_key' => $secretKey ?? bin2hex(random_bytes(16)),
            'is_active' => true,
        ]);
    }
}
