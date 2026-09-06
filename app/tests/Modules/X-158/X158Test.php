<?php

declare(strict_types=1);

namespace Tests\Modules\X158;

use App\Modules\X158\Actions\VideoHostAction;
use App\Modules\X158\Actions\VideoRenderAction;
use App\Modules\X158\Events\VideoRendered;
use App\Modules\X158\Events\VideoWatched;
use App\Modules\X158\Models\Video;
use App\Modules\X158\Models\VideoView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X158Test extends TestCase
{
    private VideoRenderAction $renderAction;

    private VideoHostAction $hostAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderAction = new VideoRenderAction;
        $this->hostAction = new VideoHostAction;
    }

    /**
     * TEST ANCHOR
     * every rendered video has a caption track;
     * the transcript of a demo video contains the demo number as digits;
     * a watch past 50% writes video.watched with depth
     * [G3-25]
     * [G9-30]
     */
    public function test_anchor_captions_present_demo_number_digits_and_watch_past_50_percent(): void
    {
        Event::fake([VideoRendered::class, VideoWatched::class]);

        $biz = TestCase::provisionTenant(['name' => 'AI Video Proposal Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $demoNumber = 1042;

        // 1. Render demo proposal video (TEST ANCHOR)
        $video = $this->renderAction->renderVideo(
            businessId: $biz->id,
            title: 'Automated Lead Qualification Demo',
            demoNumber: $demoNumber
        );

        $this->assertNotNull($video);
        $this->assertNotEmpty($video->caption_track_url, 'Every rendered video has a caption track (TEST ANCHOR)');
        $this->assertStringContainsString((string) $demoNumber, $video->transcript, 'Transcript of demo video contains demo number as digits (TEST ANCHOR)');
        Event::assertDispatched(VideoRendered::class);

        // 2. Playback under 50% (30% depth) -> does NOT dispatch video.watched past 50%
        $shortView = $this->hostAction->recordView(
            businessId: $biz->id,
            videoId: $video->id,
            viewerSessionId: 'sess_user_001',
            durationSeconds: 18,
            watchDepthPercent: 30.0
        );
        $this->assertFalse($shortView->passed_50_percent);
        Event::assertNotDispatched(VideoWatched::class);

        // 3. Playback past 50% (75% depth) -> writes view row and dispatches video.watched with depth (TEST ANCHOR)
        $deepView = $this->hostAction->recordView(
            businessId: $biz->id,
            videoId: $video->id,
            viewerSessionId: 'sess_user_002',
            durationSeconds: 45,
            watchDepthPercent: 75.0
        );

        $this->assertTrue($deepView->passed_50_percent);
        $this->assertEquals(75.0, $deepView->watch_depth_percent);

        Event::assertDispatched(VideoWatched::class, function ($event) use ($video) {
            return $event->videoId === $video->id && $event->watchDepthPercent === 75.0;
        });

        // 4. Verify database persistence
        $savedView = VideoView::where('business_id', $biz->id)->where('viewer_session_id', 'sess_user_002')->first();
        $this->assertNotNull($savedView);
        $this->assertTrue($savedView->passed_50_percent);
    }
}
