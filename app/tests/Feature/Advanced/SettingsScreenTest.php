<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\AiModel as AiModelEnum;
use App\Enums\AiTask;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Modules\X219\Actions\ModelAssignAction;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiProvider;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SettingsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        DB::statement("SET app.business_id = '{$business->id}'");

        return [$user, $business];
    }

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.settings'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_every_task_row_shows_the_platform_default_when_nothing_is_assigned(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.settings'));
        $response->assertOk();

        foreach (AiTask::cases() as $task) {
            $response->assertSee($task->defaultModel()->value);
        }

        $this->assertEquals(count(AiTask::cases()), substr_count($response->content(), 'platform default'));
    }

    public function test_a_tenant_assignment_shows_as_the_effective_model(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);

        $google = AiProvider::create(['business_id' => $bizA->id, 'provider_name' => 'google', 'status' => 'healthy']);
        $anthropic = AiProvider::create(['business_id' => $bizA->id, 'provider_name' => 'anthropic', 'status' => 'healthy']);

        // Use real model enums that match the embedding requirements
        $primary = AiModel::create(['business_id' => $bizA->id, 'provider_id' => $google->id, 'model_name' => AiModelEnum::Gpt4oMini->value]);
        $backup = AiModel::create(['business_id' => $bizA->id, 'provider_id' => $anthropic->id, 'model_name' => AiModelEnum::ClaudeHaiku45->value]);

        $assigner = new ModelAssignAction;
        $assigner->handle($bizA->id, AiTask::ReviewAnalysis->value, $primary->id, $backup->id);

        $response = $this->actingAs($userA)->get(route('advanced.settings'));
        $response->assertOk();
        $response->assertSee(AiModelEnum::Gpt4oMini->value);
        $response->assertSee('your assignment');

        // Tenant B's assignment is not shown
        [$userB, $bizB] = $this->createTenant(advanced: true);

        $googleB = AiProvider::create(['business_id' => $bizB->id, 'provider_name' => 'google', 'status' => 'healthy']);
        $anthropicB = AiProvider::create(['business_id' => $bizB->id, 'provider_name' => 'anthropic', 'status' => 'healthy']);
        $primaryB = AiModel::create(['business_id' => $bizB->id, 'provider_id' => $googleB->id, 'model_name' => AiModelEnum::ClaudeSonnet5->value]);
        $backupB = AiModel::create(['business_id' => $bizB->id, 'provider_id' => $anthropicB->id, 'model_name' => AiModelEnum::ClaudeOpus5->value]);

        $assigner->handle($bizB->id, AiTask::ReviewAnalysis->value, $primaryB->id, $backupB->id);

        // Switch back to A to assert
        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $response = $this->actingAs($userA)->get(route('advanced.settings'));
        $response->assertDontSee(AiModelEnum::ClaudeSonnet5->value);
    }

    public function test_the_links_point_at_the_real_screens(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.settings'));
        $response->assertOk();

        $response->assertSee(route('x-219.assignment-matrix'));
        $response->assertSee(route('account.settings'));
    }
}
