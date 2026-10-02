<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Enums\CredentialEnvironment;
use App\Services\Ai\ImageRequest;
use App\Services\Ai\OpenAiImageClient;
use App\Services\Config\CredentialStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OpenAiImageClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(CredentialStore::class)->set('openai_api_key', 'test', 'system', CredentialEnvironment::Live);
    }

    public function test_a_successful_call_returns_bytes_and_tokens(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-image-bytes')]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ], 200),
        ]);

        $request = new ImageRequest(AiTask::SiteImage, 'Test prompt');
        $client = new OpenAiImageClient(AiModel::GptImage25Flare);
        $response = $client->generate($request);

        $this->assertTrue($response->isUsable());
        $this->assertSame('fake-image-bytes', $response->bytes);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(20, $response->outputTokens);
        $this->assertTrue($response->usageReported);
        $this->assertNull($response->failureReason);

        Http::assertSent(function ($req) {
            return $req['model'] === 'gpt-image-2.5-flare'
                && $req['size'] === 'auto'
                && $req['output_format'] === 'jpeg';
        });
    }

    public function test_a_400_is_not_retried_and_fails_with_http_400(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'bad request'],
            ], 400),
        ]);

        $request = new ImageRequest(AiTask::SiteImage, 'Test prompt');
        $client = new OpenAiImageClient(AiModel::GptImage25Flare);
        $response = $client->generate($request);

        $this->assertFalse($response->isUsable());
        $this->assertSame('http_400', $response->failureReason);
        Http::assertSentCount(1);
    }

    public function test_a_success_with_no_usage_uses_registry_fallback_cost(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-image-bytes')]],
            ], 200),
        ]);

        // Uses the seed value from DefaultsManifest which is 600

        $request = new ImageRequest(AiTask::SiteImage, 'Test prompt');
        $client = new OpenAiImageClient(AiModel::GptImage25Flare);
        $response = $client->generate($request);

        $this->assertTrue($response->isUsable());
        $this->assertFalse($response->usageReported);
        $this->assertSame(600, $response->costInHundredthsOfCents());
    }

    public function test_a_refused_picture_logs_the_providers_reason(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Distinctive safety refusal 4956'],
            ], 400),
        ]);

        Log::spy();

        $response = (new OpenAiImageClient(AiModel::GptImage25Flare))->generate(new ImageRequest(AiTask::SiteImage, 'Test prompt'));

        $this->assertFalse($response->isUsable());
        $this->assertSame('http_400', $response->failureReason);
        Log::shouldHaveReceived('warning')
            ->with('vendor call failed', Mockery::on(fn ($context) => str_contains($context['reason'], 'Distinctive safety refusal 4956')));
    }

    public function test_an_unreachable_image_provider_is_a_failure_not_an_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Distinctive connection fault 4959'));

        Log::spy();

        $response = (new OpenAiImageClient(AiModel::GptImage25Flare))->generate(new ImageRequest(AiTask::SiteImage, 'Test prompt'));

        $this->assertSame('unreachable', $response->failureReason);
        Log::shouldHaveReceived('warning')
            ->with('vendor call failed', Mockery::on(fn ($context) => $context['reason'] === ConnectionException::class));
    }
}
