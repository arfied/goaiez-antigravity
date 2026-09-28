<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AiTask;
use App\Models\AiCall;
use App\Models\Business;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiRouter;
use App\Services\Ai\ImageRequest;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiRouterImageTest extends TestCase
{
    public function test_image_without_tenant_context_fails_and_sends_no_request(): void
    {
        Http::fake();
        Tenancy::forgetAll();

        $router = app(AiRouter::class);
        $request = new ImageRequest(AiTask::SiteImage, 'Test prompt');
        $response = $router->image($request);

        $this->assertSame('no_tenant_context', $response->failureReason);
        Http::assertNothingSent();
    }

    public function test_image_with_completion_task_is_refused(): void
    {
        Http::fake();
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $router = app(AiRouter::class);
        $request = new ImageRequest(AiTask::SiteCopy, 'Test prompt'); // A completion task
        $response = $router->image($request);

        $this->assertSame('not_an_image_task', $response->failureReason);
        Http::assertNothingSent();
    }

    public function test_image_success_writes_aicall_and_debits_credits(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $credits = app(AiCredits::class);
        // Grant credits to ensure it has enough balance
        app(\App\Services\Billing\CreditLedger::class)->record(\App\Enums\CreditProduct::Ai, \App\Enums\CreditKind::Grant, 50000, 'test');
        $balanceBefore = $credits->balanceHundredths();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ], 200),
        ]);

        $router = app(AiRouter::class);
        $request = new ImageRequest(AiTask::SiteImage, 'Test prompt');
        $response = $router->image($request);

        $this->assertTrue($response->isUsable());

        $this->assertDatabaseHas('ai_calls', [
            'task' => 'site_image',
            'output_tokens' => 20,
        ]);

        $call = AiCall::first();
        $this->assertGreaterThan(0, $call->cost_hundredths_cents);

        $balanceAfter = $credits->balanceHundredths();
        $this->assertLessThan($balanceBefore, $balanceAfter);
    }
}
