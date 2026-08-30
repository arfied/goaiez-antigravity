<?php

declare(strict_types=1);

namespace App\Modules\X158\Actions;

use App\Modules\X158\Events\VideoShared;
use App\Modules\X158\Events\VideoWatched;
use App\Modules\X158\Models\Video;
use App\Modules\X158\Models\VideoView;
use Illuminate\Support\Facades\Event;

final class VideoHostAction
{
    /**
     * Records video playback metrics.
     * A watch past 50% writes video.watched with depth (TEST ANCHOR & G3-25, G9-30).
     */
    public function recordView(
        int $businessId,
        int $videoId,
        string $viewerSessionId,
        int $durationSeconds,
        float $watchDepthPercent
    ): VideoView {
        $video = Video::where('business_id', $businessId)->findOrFail($videoId);

        $passed50 = ($watchDepthPercent > 50.0);

        $view = VideoView::create([
            'business_id' => $businessId,
            'video_id' => $video->id,
            'viewer_session_id' => $viewerSessionId,
            'watch_duration_seconds' => $durationSeconds,
            'watch_depth_percent' => $watchDepthPercent,
            'passed_50_percent' => $passed50,
        ]);

        // TEST ANCHOR: A watch past 50% writes video.watched with depth
        if ($passed50) {
            Event::dispatch(new VideoWatched($businessId, $video->id, $viewerSessionId, $watchDepthPercent));
        }

        return $view;
    }

    public function shareVideo(int $businessId, int $videoId, string $channel = 'sms'): void
    {
        $video = Video::where('business_id', $businessId)->findOrFail($videoId);
        Event::dispatch(new VideoShared($businessId, $video->id, $channel));
    }
}
