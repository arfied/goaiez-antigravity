<?php

declare(strict_types=1);

namespace App\Modules\X125\Actions;

final class FlowExplainAction
{
    /**
     * Translates flow nodes into clear, human-readable plain explanation (TEST ANCHOR).
     */
    public function explain(string $triggerEvent, array $nodes): string
    {
        $steps = [];
        $steps[] = "When '{$triggerEvent}' event occurs";

        foreach ($nodes as $index => $node) {
            $num = $index + 1;
            $type = $node['type'] ?? 'action';
            $label = $node['label'] ?? 'execute step';
            $steps[] = "Step {$num}: {$label} ({$type})";
        }

        return implode('. Then, ', $steps).'.';
    }
}
