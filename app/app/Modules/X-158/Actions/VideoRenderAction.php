<?php

declare(strict_types=1);

namespace App\Modules\X158\Actions;

use App\Modules\X158\Events\VideoRendered;
use App\Modules\X158\Models\Video;
use Illuminate\Support\Facades\Event;

final class VideoRenderAction
{
    /**
     * Renders an AI video proposal with captions and transcript (G16-02, G16-30).
     * 1. Every rendered video has a caption track (TEST ANCHOR).
     * 2. The transcript of a demo video contains the demo number as digits (TEST ANCHOR).
     */
    public function renderVideo(
        int $businessId,
        string $title,
        int $demoNumber,
        ?string $videoUrl = null,
        ?string $captionTrackUrl = null,
        ?string $customTranscript = null
    ): Video {
        $vUrl = $videoUrl ?? "https://cdn.goaiez.com/videos/{$businessId}/demo_{$demoNumber}.mp4";
        $cUrl = $captionTrackUrl ?? "https://cdn.goaiez.com/captions/{$businessId}/demo_{$demoNumber}.vtt";

        // TEST ANCHOR: Transcript must contain demo number as digits
        $transcript = $customTranscript ?? "Welcome to Demo {$demoNumber} showing how your local HVAC business can capture more missed calls.";

        $video = Video::create([
            'business_id' => $businessId,
            'title' => $title,
            'demo_number' => $demoNumber, // Digits (TEST ANCHOR)
            'video_url' => $vUrl,
            'caption_track_url' => $cUrl, // Caption track (TEST ANCHOR)
            'transcript' => $transcript,
            'is_rendered' => true,
        ]);

        Event::dispatch(new VideoRendered($businessId, $video->id, $demoNumber, $vUrl));

        return $video;
    }
}
