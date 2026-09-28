<?php

declare(strict_types=1);

namespace Tests\Modules\X177;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Ui\GbpCard;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GbpCheckAgainTest extends TestCase
{
    private $biz;

    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_published(): void
    {
        Tenancy::set($this->biz->id);

        $post = GbpPost::create([
            'business_id' => $this->biz->id,
            'connection_id' => $this->conn->id,
            'status' => 'publishing',
            'zernio_dispatch_id' => 'zp_7501',
            'content' => 'Test content',
        ]);

        Http::fake([
            'zernio.com/api/v1/posts/zp_7501' => Http::response([
                'post' => [
                    '_id' => 'zp_7501',
                    'status' => 'published',
                    'platforms' => [
                        [
                            'platform' => 'googlebusiness',
                            'status' => 'published',
                            'platformPostId' => 'g_7501',
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->call('checkAgain', $post->id);

        $this->assertEquals('posted', $post->fresh()->status);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET' && str_contains($request->url(), 'https://zernio.com/api/v1/posts/zp_7501');
        });
    }

    public function test_failed(): void
    {
        Tenancy::set($this->biz->id);

        $post = GbpPost::create([
            'business_id' => $this->biz->id,
            'connection_id' => $this->conn->id,
            'status' => 'publishing',
            'zernio_dispatch_id' => 'zp_7501',
            'content' => 'Test content',
        ]);

        Http::fake([
            'zernio.com/api/v1/posts/zp_7501' => Http::response([
                'post' => [
                    '_id' => 'zp_7501',
                    'status' => 'failed',
                    'platforms' => [
                        [
                            'platform' => 'googlebusiness',
                            'status' => 'failed',
                            'platformPostId' => null,
                            'errorMessage' => 'Photo too small 7502',
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->call('checkAgain', $post->id);

        $post = $post->fresh();
        $this->assertEquals('failed', $post->status);
        $this->assertEquals('Photo too small 7502', $post->failure_reason);
    }

    public function test_publishing(): void
    {
        Tenancy::set($this->biz->id);

        $post = GbpPost::create([
            'business_id' => $this->biz->id,
            'connection_id' => $this->conn->id,
            'status' => 'publishing',
            'zernio_dispatch_id' => 'zp_7501',
            'content' => 'Test content',
        ]);

        Http::fake([
            'zernio.com/api/v1/posts/zp_7501' => Http::response([
                'post' => [
                    '_id' => 'zp_7501',
                    'status' => 'publishing',
                    'platforms' => [
                        [
                            'platform' => 'googlebusiness',
                            'status' => 'publishing',
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->call('checkAgain', $post->id);

        $this->assertEquals('publishing', $post->fresh()->status);
    }

    public function test_already_posted(): void
    {
        Tenancy::set($this->biz->id);

        $post = GbpPost::create([
            'business_id' => $this->biz->id,
            'connection_id' => $this->conn->id,
            'status' => 'posted',
            'zernio_dispatch_id' => 'zp_7501',
            'content' => 'Test content',
        ]);

        Http::fake();

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->call('checkAgain', $post->id);

        Http::assertNothingSent();
    }

    public function test_staff(): void
    {
        Tenancy::set($this->biz->id);

        $post = GbpPost::create([
            'business_id' => $this->biz->id,
            'connection_id' => $this->conn->id,
            'status' => 'publishing',
            'zernio_dispatch_id' => 'zp_7501',
            'content' => 'Test content',
        ]);

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);

        Tenancy::setUser($staff->id);
        Tenancy::set($this->biz->id);

        Http::fake();

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->call('checkAgain', $post->id)
            ->assertForbidden();

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->call('postUpdate', $this->conn->id)
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_livewire_card_sees_check_again(): void
    {
        Tenancy::set($this->biz->id);

        $post = GbpPost::create([
            'business_id' => $this->biz->id,
            'connection_id' => $this->conn->id,
            'status' => 'publishing',
            'zernio_dispatch_id' => 'zp_7501',
            'content' => 'Test content',
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->assertSee('Check again');

        $post->update(['status' => 'posted']);

        Livewire::test(GbpCard::class, ['businessId' => $this->biz->id])
            ->assertDontSee('Check again');
    }
}
