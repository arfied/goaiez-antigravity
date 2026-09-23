<?php

declare(strict_types=1);

namespace Tests\Modules\X209\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X209\Models\FixerCommand;
use App\Modules\X209\Ui\OnetapApprovalCard;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OnetapApprovalCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        DB::statement("SET app.business_id = '{$biz->id}'");
        Tenancy::set((int) $biz->id);
        Tenancy::setUser($owner->id);

        $command = FixerCommand::create([
            'business_id' => $biz->id,
            'staff_person_id' => 999,
            'raw_command' => 'running 37 late',
            'parsed_intent' => 'job.eta_updated',
            'job_id' => 123,
            'eta_minutes_delayed' => 37,
            'consent_decision' => 'pending',
            'outbound_message_id' => null,
            'status' => 'pending_approval',
        ]);

        $this->get(route('x-209.onetap-approval-card'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertSee('running 37 late');

        Livewire::test(OnetapApprovalCard::class)
            ->call('approve', $command->id);

        $this->assertDatabaseHas('fixer_commands', [
            'id' => $command->id,
            'status' => 'executed',
        ]);

        $command2 = FixerCommand::create([
            'business_id' => $biz->id,
            'staff_person_id' => 999,
            'raw_command' => 'running 38 late',
            'parsed_intent' => 'job.eta_updated',
            'job_id' => 124,
            'eta_minutes_delayed' => 38,
            'consent_decision' => 'pending',
            'outbound_message_id' => null,
            'status' => 'pending_approval',
        ]);

        Livewire::test(OnetapApprovalCard::class)
            ->call('delegate', $command2->id);

        $this->assertDatabaseHas('fixer_commands', [
            'id' => $command2->id,
            'status' => 'escalated',
        ]);

        $otherBiz = $this->provisionTenant([]);
        $otherCommand = FixerCommand::create([
            'business_id' => $otherBiz->id,
            'staff_person_id' => 111,
            'raw_command' => 'running 39 late',
            'parsed_intent' => 'job.eta_updated',
            'job_id' => 125,
            'eta_minutes_delayed' => 39,
            'consent_decision' => 'pending',
            'outbound_message_id' => null,
            'status' => 'pending_approval',
        ]);

        Tenancy::set((int) $biz->id);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(OnetapApprovalCard::class)
            ->call('approve', $otherCommand->id);
    }
}
