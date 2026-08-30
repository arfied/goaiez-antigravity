<?php

declare(strict_types=1);

namespace App\Modules\X213\Actions;

use App\Modules\X213\Events\VisionCheckFlagged;
use App\Modules\X213\Events\VisionCheckPassed;
use App\Modules\X213\Models\VisionCheck;
use Illuminate\Support\Facades\Event;

final class VisionCheckRunAction
{
    /**
     * Executes vision checklist quality check.
     * Every check stores screenshot artifact and checklist version (TEST ANCHOR).
     * A pass with no artifact DID NOT HAPPEN (TEST ANCHOR).
     */
    public function runCheck(
        int $businessId,
        string $contentRef,
        string $checklistVersion,
        ?string $screenshotArtifactUrl = null,
        array $detectedDefects = []
    ): VisionCheck {
        // TEST ANCHOR: A pass with no artifact DID NOT HAPPEN
        $hasValidArtifact = ! empty($screenshotArtifactUrl);
        $hasNoDefects = empty($detectedDefects);

        $passed = ($hasValidArtifact && $hasNoDefects);

        if (! $hasValidArtifact) {
            $detectedDefects[] = 'Missing required screenshot artifact (a pass with no artifact did not happen)';
        }

        $check = VisionCheck::create([
            'business_id' => $businessId,
            'content_ref' => $contentRef,
            'screenshot_artifact_url' => $screenshotArtifactUrl,
            'checklist_version' => $checklistVersion,
            'passed' => $passed,
            'defect_flags' => $detectedDefects,
        ]);

        if ($passed) {
            Event::dispatch(new VisionCheckPassed($businessId, $check->id, $contentRef, (string) $screenshotArtifactUrl));
        } else {
            Event::dispatch(new VisionCheckFlagged($businessId, $check->id, $contentRef, $detectedDefects));
        }

        return $check;
    }
}
