<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

final class LoomDashboardAction
{
    /**
     * [G7-19]
     */
    public function embedLoomUrl(string $loomId): string
    {
        // R245: Seam for Loom or client dashboard media
        return "https://www.loom.com/embed/{$loomId}";
    }
}
