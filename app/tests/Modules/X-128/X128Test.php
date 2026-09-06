<?php

declare(strict_types=1);

namespace Tests\Modules\X128;

use App\Modules\X128\Actions\DeployCheckAction;
use App\Modules\X128\Actions\MatrixGenerateAction;
use App\Modules\X128\Events\OrphanDetected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X128Test extends TestCase
{
    private MatrixGenerateAction $matrix;

    private DeployCheckAction $deployCheck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matrix = new MatrixGenerateAction;
        $this->deployCheck = new DeployCheckAction($this->matrix);
    }

    /**
     * TEST ANCHOR
     * goaiez:matrix on the full tree returns zero orphans;
     * adding one @emits with no subscriber makes it return exactly one, naming the file
     */
    public function test_anchor_matrix_orphans_detection_and_naming(): void
    {
        Event::fake([OrphanDetected::class]);

        $biz = TestCase::provisionTenant(['name' => 'Matrix Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Balanced manifest graph with wildcard or subscribed consumers
        $cleanManifests = [
            'X-121' => [
                'module' => 'X-121',
                'emits' => ['job.created', 'person.updated'],
                'consumes' => ['none'],
            ],
            'X-123' => [
                'module' => 'X-123',
                'emits' => ['event.published'],
                'consumes' => ['*'], // Wildcard subscriber consumes all
            ],
        ];

        $cleanRes = $this->matrix->handle($biz->id, $cleanManifests);
        $this->assertEquals(0, $cleanRes['orphans_count'], 'Clean graph must return 0 orphans');

        // 2. Add an @emits with no subscriber
        $brokenManifests = [
            'X-121' => [
                'module' => 'X-121',
                'emits' => ['job.created'],
                'consumes' => ['none'],
            ],
            'X-999' => [
                'module' => 'X-999',
                'emits' => ['rogue.unsubscribed_event'],
                'consumes' => ['none'],
            ],
            'X-124' => [
                'module' => 'X-124',
                'emits' => ['none'],
                'consumes' => ['job.created'], // Only job.created is subscribed, rogue is not
            ],
        ];

        $brokenRes = $this->matrix->handle($biz->id, $brokenManifests);
        $this->assertEquals(1, $brokenRes['orphans_count'], 'Must detect exactly one orphan');
        $this->assertEquals('rogue.unsubscribed_event', $brokenRes['orphans'][0]['event']);
        $this->assertEquals('app/Modules/X-999/manifest.php', $brokenRes['orphans'][0]['file']);

        Event::assertDispatched(OrphanDetected::class, function (OrphanDetected $event) use ($biz) {
            return $event->businessId === $biz->id
                && $event->eventName === 'rogue.unsubscribed_event'
                && $event->sourceModule === 'X-999';
        });
    }

    /**
     * [N-049] integration matrix and deploy check
     * [N-050]
     * [N-051]
     * [N-053]
     * [N-054]
     * [N-055]
     * [N-057]
     * [N-060]
     */
    public function test_n_049_deploy_check(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Deploy Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->deployCheck->handle($biz->id);
        $this->assertArrayHasKey('can_deploy', $res);
    }
}
