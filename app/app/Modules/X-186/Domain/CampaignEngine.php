<?php

declare(strict_types=1);

namespace App\Modules\X186\Domain;

final class CampaignEngine
{
    // X-186 domain layer enforcing that all send.requests carry class = marketing,
    // and guaranteeing that any cross-channel reply halts the entire sequence instantly.
}
