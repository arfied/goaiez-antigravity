<?php

declare(strict_types=1);

namespace App\Modules\X128\Actions;

use App\Modules\X128\Events\OrphanDetected;
use App\Modules\X128\Models\IntegrationMatrix;
use Illuminate\Support\Facades\Event;

final class MatrixGenerateAction
{
    public function __construct() {}

    public function handle(int $businessId, ?array $manifestOverride = null): array
    {
        $manifests = $manifestOverride ?? $this->loadManifests();

        $emitters = [];
        $subscribers = [];
        $wildcardSubscribers = [];

        foreach ($manifests as $mod => $m) {
            foreach ($m['emits'] ?? [] as $evt) {
                if ($evt !== 'none' && $evt !== '') {
                    $emitters[$evt][] = $mod;
                }
            }
            foreach ($m['consumes'] ?? [] as $evt) {
                if ($evt === '*') {
                    $wildcardSubscribers[] = $mod;
                } elseif ($evt !== 'none' && $evt !== '') {
                    $subscribers[$evt][] = $mod;
                }
            }
        }

        $orphans = [];

        // Check for emitted events with no subscribers (unless wildcard subscriber exists)
        foreach ($emitters as $evt => $mods) {
            $hasSubscriber = isset($subscribers[$evt]) && count($subscribers[$evt]) > 0;
            $hasWildcard = count($wildcardSubscribers) > 0;

            if (! $hasSubscriber && ! $hasWildcard) {
                foreach ($mods as $mod) {
                    $orphans[] = [
                        'event' => $evt,
                        'module' => $mod,
                        'file' => "app/Modules/{$mod}/manifest.php",
                        'reason' => "Event '{$evt}' emitted by {$mod} has no declared subscriber",
                    ];

                    Event::dispatch(new OrphanDetected(
                        businessId: $businessId,
                        eventName: $evt,
                        sourceModule: $mod,
                        reason: "Event '{$evt}' emitted by {$mod} has no subscriber"
                    ));
                }
            }
        }

        $matrixRecord = IntegrationMatrix::create([
            'business_id' => $businessId,
            'matrix_data' => [
                'emitters' => $emitters,
                'subscribers' => $subscribers,
                'wildcards' => $wildcardSubscribers,
                'orphans' => $orphans,
            ],
            'orphans_count' => count($orphans),
            'violations_count' => 0,
            'checksum' => hash('sha256', json_encode($manifests)),
            'generated_at' => now(),
        ]);

        return [
            'id' => $matrixRecord->id,
            'orphans' => $orphans,
            'orphans_count' => count($orphans),
            'emitters_count' => count($emitters),
            'subscribers_count' => count($subscribers),
        ];
    }

    private function loadManifests(): array
    {
        $out = [];
        $pattern = base_path('app/Modules/*/manifest.php');
        foreach (glob($pattern) ?: [] as $path) {
            $data = require $path;
            if (is_array($data) && isset($data['module'])) {
                $out[$data['module']] = $data;
            }
        }

        return $out;
    }
}
