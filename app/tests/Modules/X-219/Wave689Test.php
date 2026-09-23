<?php

declare(strict_types=1);

namespace Tests\Modules\X219;

use App\Enums\AiModel as AiModelEnum;
use App\Enums\AiTask;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X219\Actions\RosterSeedAction;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiModuleAssignment;
use App\Modules\X219\Models\AiProvider;
use App\Modules\X219\Ui\AssignmentMatrix;
use App\Modules\X219\Ui\RosterAdmin;
use App\Services\Ai\AiSpend;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class Wave689Test extends TestCase
{
    public function test_seed_is_idempotent()
    {
        $biz = TestCase::provisionTenant();
        DB::statement("SET app.business_id = '{$biz->id}'");

        $action = app(RosterSeedAction::class);
        $res1 = $action->handle($biz->id);

        $this->assertGreaterThan(0, $res1['providers']);
        $this->assertGreaterThan(0, $res1['models']);

        $res2 = $action->handle($biz->id);

        $this->assertEquals($res1['providers'], $res2['providers']);
        $this->assertEquals($res1['models'], $res2['models']);

        $this->assertEquals($res1['providers'], AiProvider::where('business_id', $biz->id)->count());
        $this->assertEquals($res1['models'], AiModel::where('business_id', $biz->id)->count());
    }

    public function test_model_for_uses_tenant_assignment()
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);

        app(RosterSeedAction::class)->handle($biz->id);
        AiProvider::where('business_id', $biz->id)->update(['status' => 'healthy']);
        $models = AiModel::where('business_id', $biz->id)->get();
        $primary = $models->firstWhere('model_name', AiModelEnum::ClaudeOpus5->value);
        $backup = $models->firstWhere('model_name', AiModelEnum::ClaudeHaiku45->value);

        AiModuleAssignment::create([
            'business_id' => $biz->id,
            'target_module' => AiTask::ReviewAnalysis->value,
            'primary_model_id' => $primary->id,
            'backup_model_id' => $backup->id,
        ]);

        $spend = app(AiSpend::class);
        $resolved = $spend->modelFor(AiTask::ReviewAnalysis);

        $this->assertEquals(AiModelEnum::ClaudeOpus5, $resolved);
    }

    public function test_model_for_rejects_embedding_mismatch()
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);

        app(RosterSeedAction::class)->handle($biz->id);
        $models = AiModel::where('business_id', $biz->id)->get();
        $primary = $models->firstWhere('model_name', AiModelEnum::ClaudeOpus5->value); // not embedding
        $backup = $models->firstWhere('model_name', AiModelEnum::ClaudeHaiku45->value);

        AiModuleAssignment::create([
            'business_id' => $biz->id,
            'target_module' => AiTask::KnowledgeEmbedding->value, // expects embedding
            'primary_model_id' => $primary->id,
            'backup_model_id' => $backup->id,
        ]);

        $spend = app(AiSpend::class);
        $resolved = $spend->modelFor(AiTask::KnowledgeEmbedding);

        // Fallback to enum default because no registry key is set
        $this->assertEquals(AiTask::KnowledgeEmbedding->defaultModel(), $resolved);
    }

    public function test_model_for_uses_registry_when_no_assignment()
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);

        app(\App\Services\Config\DefaultsRegistry::class)->set(AiTask::ReviewAnalysis->settingKey(), AiModelEnum::ClaudeSonnet5->value, 'test');

        $spend = app(AiSpend::class);
        $resolved = $spend->modelFor(AiTask::ReviewAnalysis);

        $this->assertEquals(AiModelEnum::ClaudeSonnet5, $resolved);
    }

    public function test_roster_admin_screen()
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        Livewire::actingAs($owner)
            ->test(RosterAdmin::class, ['businessId' => $biz->id])
            ->call('seed')
            ->assertDispatched('toast');

        $this->assertGreaterThan(0, AiProvider::where('business_id', $biz->id)->count());
    }

    public function test_assignment_matrix_screen_happy_path()
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        app(RosterSeedAction::class)->handle($biz->id);
        $models = AiModel::where('business_id', $biz->id)->get();
        $primary = $models->firstWhere('model_name', AiModelEnum::ClaudeOpus5->value);
        $backup = $models->firstWhere('model_name', AiModelEnum::Gpt4oMini->value);

        Livewire::actingAs($owner)
            ->test(AssignmentMatrix::class, ['businessId' => $biz->id])
            ->set('primaryModelForTask.'.AiTask::ReviewAnalysis->value, $primary->id)
            ->set('backupModelForTask.'.AiTask::ReviewAnalysis->value, $backup->id)
            ->call('assign', AiTask::ReviewAnalysis->value)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('ai_module_assignments', [
            'business_id' => $biz->id,
            'target_module' => AiTask::ReviewAnalysis->value,
            'primary_model_id' => $primary->id,
            'backup_model_id' => $backup->id,
        ]);
    }

    public function test_assignment_matrix_screen_renders_r237()
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        app(RosterSeedAction::class)->handle($biz->id);
        $models = AiModel::where('business_id', $biz->id)->get();
        $primary = $models->firstWhere('model_name', AiModelEnum::ClaudeOpus5->value);
        $backup = $models->firstWhere('model_name', AiModelEnum::ClaudeSonnet5->value); // same provider

        Livewire::actingAs($owner)
            ->test(AssignmentMatrix::class, ['businessId' => $biz->id])
            ->set('primaryModelForTask.'.AiTask::ReviewAnalysis->value, $primary->id)
            ->set('backupModelForTask.'.AiTask::ReviewAnalysis->value, $backup->id)
            ->call('assign', AiTask::ReviewAnalysis->value)
            ->assertHasErrors(['assign.'.AiTask::ReviewAnalysis->value]);
    }
}
