<?php

declare(strict_types=1);

namespace App\Enums;

enum FlowRunStatus: string
{
    case Success = 'success';
    case Error = 'error';
    /**
     * Simulated is never persisted (FlowSimulateAction returns an array and writes no row).
     */
    case Simulated = 'simulated';
}
