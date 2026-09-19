<?php

declare(strict_types=1);

namespace App\Modules\X112\Domain;

final class TaskVisibilityEngine
{
    /**
     * [G7-13] task visibility per client
     */
    public function getVisibleTasks(string $clientId, array $allTasks): array
    {
        // R245: Filter tasks so clients only see their own.
        return array_filter($allTasks, function ($task) use ($clientId) {
            return ($task['client_id'] ?? null) === $clientId;
        });
    }
}
