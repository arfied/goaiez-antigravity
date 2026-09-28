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
        $this->actingAs(User::find($this->biz->owner_user_id));
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

    public function test_with_https_image_sent(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6101', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6101', 'errorMessage' => null]]]], 201),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6101', 'update', 'https://cdn.example.test/p/6101.jpg');

        $this->assertEquals('posted', $result['status']);
        $post = GbpPost::find($result['post_id']);
        $this->assertEquals('https://cdn.example.test/p/6101.jpg', $post->image_url);

        Http::assertSent(function ($request) {
            return $request['mediaItems'][0]['type'] === 'image'
                && $request['mediaItems'][0]['url'] === 'https://cdn.example.test/p/6101.jpg';
        });
    }

    public function test_without_image_no_media_items(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6102', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6102', 'errorMessage' => null]]]], 201),
        ]);

        $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6102', 'update', null);

        Http::assertSent(function ($request) {
            return ! isset($request['mediaItems']);
        });
    }

    public function test_refused_images(): void
    {
        Http::fake();

        $initialCount = GbpPost::count();

        $result1 = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6102', 'update', 'http://cdn.example.test/p/6102.jpg');
        $this->assertEquals('refused_image', $result1['status']);

        $result2 = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6103', 'update', 'https://cdn.example.test/p/6103.gif');
        $this->assertEquals('refused_image', $result2['status']);

        Http::assertNothingSent();
        $this->assertEquals($initialCount, GbpPost::count());
    }

    public function test_image_rejected_by_zernio(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6105', 'status' => 'failed', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'failed', 'platformPostId' => null, 'errorMessage' => 'Image too small.']]]], 207),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test small image', 'update', 'https://cdn.example.test/p/6105.jpg');

        $this->assertEquals('failed', $result['status']);
        $post = GbpPost::find($result['post_id']);
        $this->assertEquals('Image too small.', $post->failure_reason);
    }

    public function test_livewire_card_with_image(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6104', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6104', 'errorMessage' => null]]]], 201),
        ]);

        Tenancy::set($this->biz->id);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->set('postContent.'.$this->conn->id, 'With image 6104')
            ->set('postImage.'.$this->conn->id, 'https://cdn.example.test/p/6104.png')
            ->call('postUpdate', $this->conn->id);

        $this->assertDatabaseHas('gbp_posts', [
            'content' => 'With image 6104',
            'status' => 'posted',
            'image_url' => 'https://cdn.example.test/p/6104.png',
        ]);
    }

    public function test_cta_book_and_url_sent_and_stored(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6301', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6301', 'errorMessage' => null]]]], 201),
        ]);

        $result = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6301 CTA', 'update', null, 'BOOK', 'https://book.example.test/6301');

        $this->assertEquals('posted', $result['status']);
        $post = GbpPost::find($result['post_id']);
        $this->assertEquals('BOOK', $post->cta_type);
        $this->assertEquals('https://book.example.test/6301', $post->cta_url);

        Http::assertSent(function ($request) {
            $data = $request['platforms'][0]['platformSpecificData'] ?? null;

            return $data !== null
                && $data['topicType'] === 'STANDARD'
                && $data['callToAction']['type'] === 'BOOK'
                && $data['callToAction']['url'] === 'https://book.example.test/6301';
        });
    }

    public function test_no_cta_means_no_platform_specific_data(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6302', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6302', 'errorMessage' => null]]]], 201),
        ]);

        $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6302 CTA null');

        Http::assertSent(function ($request) {
            return ! isset($request['platforms'][0]['platformSpecificData']);
        });
    }

    public function test_cta_validation_refuses_partial_or_invalid(): void
    {
        Http::fake();

        $initialCount = GbpPost::count();

        // type without url
        $result1 = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6303 A', 'update', null, 'BOOK', null);
        $this->assertEquals('refused_cta', $result1['status']);

        // url without type
        $result2 = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6303 B', 'update', null, null, 'https://book.example.test/6303');
        $this->assertEquals('refused_cta', $result2['status']);

        // http url
        $result3 = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6303 C', 'update', null, 'BOOK', 'http://book.example.test/6303');
        $this->assertEquals('refused_cta', $result3['status']);

        // invalid type
        $result4 = $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6303 D', 'update', null, 'ALERT', 'https://book.example.test/6303');
        $this->assertEquals('refused_cta', $result4['status']);

        Http::assertNothingSent();
        $this->assertEquals($initialCount, GbpPost::count());
    }

    public function test_image_and_cta_together(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6304', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6304', 'errorMessage' => null]]]], 201),
        ]);

        $this->postAction->post($this->biz->id, $this->conn->id, 'Test 6304 Both', 'update', 'https://cdn.example.test/p/6304.jpg', 'SHOP', 'https://shop.example.test/6304');

        Http::assertSent(function ($request) {
            $data = $request['platforms'][0]['platformSpecificData'] ?? null;

            return isset($request['mediaItems'])
                && $request['mediaItems'][0]['type'] === 'image'
                && $data !== null
                && $data['topicType'] === 'STANDARD'
                && $data['callToAction']['type'] === 'SHOP';
        });
    }

    public function test_livewire_card_with_cta(): void
    {
        Http::fake([
            'zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'zp_6305', 'status' => 'published', 'platforms' => [['platform' => 'googlebusiness', 'status' => 'published', 'platformPostId' => 'g_6305', 'errorMessage' => null]]]], 201),
        ]);

        Tenancy::set($this->biz->id);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->set('postContent.'.$this->conn->id, 'Call us 6305')
            ->set('postCtaType.'.$this->conn->id, 'CALL')
            ->set('postCtaUrl.'.$this->conn->id, 'https://call.example.test/6302')
            ->call('postUpdate', $this->conn->id);

        $this->assertDatabaseHas('gbp_posts', [
            'content' => 'Call us 6305',
            'status' => 'posted',
            'cta_type' => 'CALL',
            'cta_url' => 'https://call.example.test/6302',
        ]);
    }
}
