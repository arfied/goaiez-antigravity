<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

final class AgentPerformanceGraphView
{
    /**
     * [G5-51] named in the header; the minute-by-minute graph is an X-194 view
     */
    public function renderData(): array
    {
        // R245: The minute-by-minute graph leverages X-194. C-Agent provides the data structure.
        return [
            ['minute' => '10:01', 'calls' => 5],
            ['minute' => '10:02', 'calls' => 12],
        ];
    }
}
