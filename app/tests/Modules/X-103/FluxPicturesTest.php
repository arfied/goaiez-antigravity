<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Models\User;
use App\Modules\X103\Actions\SiteImageGenerateAction;
use App\Services\Ai\FalImageClient;
use App\Services\Ai\ImageRequest;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class FluxPicturesTest extends TestCase
{
    use RefreshesTenantDatabase;

    private const JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function falAnswer(string $url): void
    {
        Http::fake([
            'fal.run/*' => Http::response([
                'images' => [['url' => $url, 'width' => 1024, 'height' => 576, 'content_type' => 'image/jpeg']],
                'seed' => 1,
                'has_nsfw_concepts' => [false],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
    }

    public function test_flux_returns_an_inline_picture_and_costs_one_megapixel(): void
    {
        config(['credentials.fal_api_key' => 'fake-fal']);
        $this->falAnswer('data:image/jpeg;base64,'.self::JPEG);

        $response = (new FalImageClient(AiModel::FluxSchnell))->generate(new ImageRequest(AiTask::SiteImage, 'a painter at work 7801'));

        $this->assertTrue($response->isUsable());
        $this->assertSame(base64_decode(self::JPEG), $response->bytes);
        $this->assertSame(1_000_000, $response->outputTokens);
        $this->assertSame(30, $response->costInHundredthsOfCents());
        Http::assertSent(fn ($r) => $r->url() === 'https://fal.run/fal-ai/flux/schnell'
            && $r->hasHeader('Authorization', 'Key fake-fal')
            && $r['sync_mode'] === true
            && $r['prompt'] === 'a painter at work 7801');
    }

    public function test_a_remote_picture_url_is_refused_and_never_fetched(): void
    {
        config(['credentials.fal_api_key' => 'fake-fal']);
        $this->falAnswer('https://v3.fal.media/files/elsewhere-7802.jpg');

        $response = (new FalImageClient(AiModel::FluxSchnell))->generate(new ImageRequest(AiTask::SiteImage, 'p'));

        $this->assertFalse($response->isUsable());
        $this->assertSame('no_inline_image', $response->failureReason);
        Http::assertSentCount(1);
    }

    /**
     * Two tests, not one: CredentialStore memoises each key for the life of the process, so a key switched on half-way
     * through one test would never be seen.
     */
    private function tenantWithFakes(?string $falKey): int
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);
        config(['credentials.openai_api_key' => 'fake-openai', 'credentials.fal_api_key' => $falKey]);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => self::JPEG]]], 200, ['Content-Type' => 'application/json']),
            'fal.run/*' => Http::response(['images' => [['url' => 'data:image/jpeg;base64,'.self::JPEG, 'width' => 1024, 'height' => 576]]], 200, ['Content-Type' => 'application/json']),
        ]);

        return (int) $biz->id;
    }

    public function test_site_pictures_keep_using_openai_while_there_is_no_fal_key(): void
    {
        $businessId = $this->tenantWithFakes(null);

        $result = app(SiteImageGenerateAction::class)->handle($businessId, 'a tidy kitchen 7803');

        $this->assertSame('generated', $result['status']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openai.com/v1/images/generations'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'fal.run'));
    }

    public function test_site_pictures_use_flux_once_a_fal_key_exists(): void
    {
        $businessId = $this->tenantWithFakes('fake-fal');

        $result = app(SiteImageGenerateAction::class)->handle($businessId, 'a tidy kitchen 7804');

        $this->assertSame('generated', $result['status']);
        Storage::disk('local')->assertExists($result['path']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fal.run/fal-ai/flux/schnell') && str_contains((string) $r['prompt'], 'a tidy kitchen 7804'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.openai.com'));
    }
}
