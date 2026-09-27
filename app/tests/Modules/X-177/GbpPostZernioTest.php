<?php

declare(strict_types=1);

namespace Tests\Modules\X177;

use App\Models\Location;
use App\Models\User;
use App\Modules\X177\Actions\GbpPostAction;
use App\Modules\X177\Events\GbpPosted;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Ui\GbpCard;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GbpPostZernioTest extends TestCase
{
    private GbpPostAction $postAction;

    private $biz;

    private $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postAction = new GbpPostAction;

        $this->biz = TestCase::provisionTenant(['name' => 'GBP Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$this->biz->id}'");

        $loc = Location::factory()->create(['business_id' => $this->biz->id]);

        $this->conn = GbpConnection::create([
            'business_id' => $this->biz->id,
            'account_id' => 'acc_gbp_9902',
            'location_id' => $loc->id,
            'profile_status' => 'active',
            'account_ref' => 'acc_zernio_4956',
            'status' => 'connected',
        ]);

        app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');
        config(['credentials.zernio_api_key' => 'test-key']);
    }

    public function test_request_zernio_receives(): void
    {
        Event::fake([GbpPosted::class]);
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_4956', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_4956', 'errorMessage' => null]]]], 201),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test content a');

        $this->assertEquals('posted', $result['status']);
        $this->assertEquals('zp_4956', $result['zernio_dispatch_id']);

        $post = GbpPost::find($result['post_id']);
        $this->assertEquals('posted', $post->status);
        $this->assertEquals('zp_4956', $post->zernio_dispatch_id);

        Http::assertSent(function ($request) use ($post) {
            return str_contains($request->url(), 'zernio.com/api/v1/posts')
                && $request->method() === 'POST'
                && $request->header('Idempotency-Key')[0] === 'gbp-post-'.$post->id
                && $request['platforms'][0]['platform'] === 'googlebusiness'
                && $request['platforms'][0]['accountId'] === 'acc_zernio_4956'
                && $request['publishNow'] === true
                && $request['content'] === 'Test content a';
        });
    }

    public function test_207_scheduled(): void
    {
        Event::fake([GbpPosted::class]);
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_4956', 'status' => 'scheduled', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'scheduled', 'platformPostId' => null, 'errorMessage' => null]]]], 207),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test content b');
        $this->assertEquals('publishing', $result['status']);
        Event::assertNotDispatched(GbpPosted::class);
    }

    public function test_207_failed_with_error(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_4956', 'status' => 'failed', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'failed', 'platformPostId' => null, 'errorMessage' => 'Request contains an invalid argument.']]]], 207),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test content c');
        $this->assertEquals('failed', $result['status']);

        $post = GbpPost::find($result['post_id']);
        $this->assertEquals('failed', $post->status);
        $this->assertEquals('Request contains an invalid argument.', $post->failure_reason);
    }

    public function test_403_account_disconnected(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['code' => 'ACCOUNT_DISCONNECTED'], 403),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test content d');
        $this->assertEquals('failed', $result['status']);
    }

    public function test_no_account_ref(): void
    {
        Http::fake();
        $this->conn->update(['account_ref' => null, 'status' => 'disconnected']);
        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test content e');
        $this->assertEquals('not_connected', $result['status']);
        Http::assertNothingSent();
    }

    public function test_risk_and_suspension_gates(): void
    {
        Http::fake();
        $this->conn->update(['profile_status' => 'suspended']);
        $this->postAction->post($this->biz->id, $this->conn->id, 'Test content f');

        $this->conn->update(['profile_status' => 'active']);
        $this->postAction->post($this->biz->id, $this->conn->id, 'guaranteed ranking #1');

        Http::assertNothingSent();
    }

    public function test_livewire_card(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_4957', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_4957', 'errorMessage' => null]]]], 201),
        ]);

        Tenancy::set($this->biz->id);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->set('postContent.'.$this->conn->id, 'Open late 4957')
            ->call('postUpdate', $this->conn->id);

        $this->assertDatabaseHas('gbp_posts', [
            'content' => 'Open late 4957',
            'status' => 'posted',
        ]);

        $user = User::find($this->biz->owner_user_id);
        $this->actingAs($user)
            ->get(route('x-177.gbp-card'))
            ->assertOk()
            ->assertSee('Post to Google');
    }
}
