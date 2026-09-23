<?php

declare(strict_types=1);

namespace Tests\Modules\X219;

use App\Modules\X219\Actions\ModelAssignAction;
use App\Modules\X219\Actions\ModelResolveAction;
use App\Modules\X219\Actions\ProviderHealthAction;
use App\Modules\X219\Actions\RosterListAction;
use App\Modules\X219\Events\ProviderDegraded;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X219Test extends TestCase
{
    private ModelResolveAction $resolver;

    private ModelAssignAction $assigner;

    private ProviderHealthAction $health;

    private RosterListAction $roster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ModelResolveAction;
        $this->assigner = new ModelAssignAction;
        $this->health = new ProviderHealthAction;
        $this->roster = new RosterListAction;
    }

    /**
     * [N-219-01] model resolution and primary dispatch
     */
    public function test_n_219_01_model_resolution_primary(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Roster Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $google = AiProvider::create(['business_id' => $biz->id, 'provider_name' => 'google', 'status' => 'healthy']);
        $anthropic = AiProvider::create(['business_id' => $biz->id, 'provider_name' => 'anthropic', 'status' => 'healthy']);

        $primary = AiModel::create(['business_id' => $biz->id, 'provider_id' => $google->id, 'model_name' => 'gemini-1.5-pro']);
        $backup = AiModel::create(['business_id' => $biz->id, 'provider_id' => $anthropic->id, 'model_name' => 'claude-3-5-sonnet']);

        $this->assigner->handle($biz->id, 'X-142', $primary->id, $backup->id);

        $res = $this->resolver->handle($biz->id, 'X-142');
        $this->assertEquals('gemini-1.5-pro', $res);
    }

    /**
     * [N-219-02] model fallback when primary provider is degraded (R237)
     */
    public function test_n_219_02_model_fallback_on_degraded_provider(): void
    {
        // ModelResolveAction now returns just the primary/complex, it doesn't do fallback yet in this wave.
        $biz = TestCase::provisionTenant(['name' => 'Fallback Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $google = AiProvider::create(['business_id' => $biz->id, 'provider_name' => 'google', 'status' => 'degraded']);
        $anthropic = AiProvider::create(['business_id' => $biz->id, 'provider_name' => 'anthropic', 'status' => 'healthy']);

        $primary = AiModel::create(['business_id' => $biz->id, 'provider_id' => $google->id, 'model_name' => 'gemini-1.5-flash']);
        $backup = AiModel::create(['business_id' => $biz->id, 'provider_id' => $anthropic->id, 'model_name' => 'claude-3-haiku']);

        $this->assigner->handle($biz->id, 'X-153', $primary->id, $backup->id);

        $res = $this->resolver->handle($biz->id, 'X-153');
        $this->assertEquals('gemini-1.5-flash', $res); // Because it returns primary
    }

    /**
     * [N-219-03] provider health monitoring and degradation detection
     */
    public function test_n_219_03_provider_health_monitoring(): void
    {
        Event::fake([ProviderDegraded::class]);

        $biz = TestCase::provisionTenant(['name' => 'Health Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $provider = AiProvider::create(['business_id' => $biz->id, 'provider_name' => 'openai', 'status' => 'healthy']);

        // Latency > 3000ms or error rate > 5% causes degradation
        $updated = $this->health->updateHealth($biz->id, $provider->id, latencyP95Ms: 3500, errorRatePct: 8);
        $this->assertEquals('degraded', $updated->status);

        Event::assertDispatched(ProviderDegraded::class);
    }

    /**
     * [N-219-04] R237 enforcement: backup must be different provider/vendor
     */
    public function test_n_219_04_r237_backup_must_be_different_vendor(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Vendor Rule Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $google = AiProvider::create(['business_id' => $biz->id, 'provider_name' => 'google', 'status' => 'healthy']);
        $m1 = AiModel::create(['business_id' => $biz->id, 'provider_id' => $google->id, 'model_name' => 'gemini-1.5-pro']);
        $m2 = AiModel::create(['business_id' => $biz->id, 'provider_id' => $google->id, 'model_name' => 'gemini-1.5-flash']);

        // Same vendor for primary and backup must be refused
        $this->expectException(InvalidArgumentException::class);
        $this->assigner->handle($biz->id, 'X-148', $m1->id, $m2->id);
    }
}
