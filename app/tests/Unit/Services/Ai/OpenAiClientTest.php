<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Enums\CredentialEnvironment;
use App\Services\Ai\AiRequest;
use App\Services\Ai\OpenAiClient;
use App\Services\Config\CredentialStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(CredentialStore::class)->set('openai_api_key', 'test', 'system', CredentialEnvironment::Live);
    }

    public function test_an_open_schema_is_sent_without_strict(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"blocks":[],"explanation":"x"}']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ], 200),
        ]);

        $schema = [
            'type' => 'object',
            'properties' => [
                'blocks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                        ],
                        'required' => ['type'],
                        'additionalProperties' => true,
                    ],
                ],
                'explanation' => ['type' => 'string'],
            ],
            'required' => ['blocks', 'explanation'],
        ];

        $request = new AiRequest(AiTask::SiteCopy, 'Test prompt', null, $schema);

        $client = new OpenAiClient(AiModel::Gpt4oMini);
        $client->complete($request);

        Http::assertSent(fn ($req) => $req['response_format']['json_schema']['strict'] === false);
    }

    public function test_a_closed_schema_is_sent_strict(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ], 200),
        ]);

        $schema = [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
            ],
            'required' => ['ok'],
            'additionalProperties' => false,
        ];

        $request = new AiRequest(AiTask::SiteCopy, 'Test prompt', null, $schema);

        $client = new OpenAiClient(AiModel::Gpt4oMini);
        $client->complete($request);

        Http::assertSent(fn ($req) => $req['response_format']['json_schema']['strict'] === true);
    }

    public function test_a_400_carries_the_providers_message_into_the_log(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Distinctive schema fault 4944'],
            ], 400),
        ]);

        Log::spy();

        $request = new AiRequest(AiTask::SiteCopy, 'Test prompt', null, []);

        $client = new OpenAiClient(AiModel::Gpt4oMini);
        $response = $client->complete($request);

        $this->assertSame('http_400', $response->failureReason);

        Log::shouldHaveReceived('warning')
            ->with('vendor call failed', Mockery::on(fn ($context) => str_contains($context['reason'], 'Distinctive schema fault 4944')));
    }

    public function test_a_401_logs_its_code_and_never_the_providers_message(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Incorrect API key provided: sk-ab****4957. Distinctive auth text 4958'],
            ], 401),
        ]);

        Log::spy();

        $request = new AiRequest(AiTask::SiteCopy, 'Test prompt', null, []);
        $response = (new OpenAiClient(AiModel::Gpt4oMini))->complete($request);

        $this->assertSame('http_401', $response->failureReason);
        Log::shouldHaveReceived('warning')
            ->with('vendor call failed', Mockery::on(fn ($context) => $context['reason'] === 'http_401'));
        Log::shouldNotHaveReceived('warning', [Mockery::any(), Mockery::on(fn ($context) => str_contains((string) ($context['reason'] ?? ''), 'Distinctive auth text 4958'))]);
    }
}
