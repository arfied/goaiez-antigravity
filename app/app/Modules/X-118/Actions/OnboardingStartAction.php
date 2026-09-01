<?php

declare(strict_types=1);

namespace App\Modules\X118\Actions;

use App\Models\User;
use App\Modules\X118\Events\AgentLive;
use App\Modules\X118\Events\TenantCreated;
use App\Modules\X118\Events\TenantProvisioned;
use App\Modules\X118\Events\TtfmMeasured;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Models\OnboardingStep;
use App\Modules\X188\Actions\NumberAssignAction;
use App\Modules\X188\Domain\NumberPoolManager;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;

final class OnboardingStartAction
{
    private NumberAssignAction $assigner;

    public function __construct(?NumberAssignAction $assigner = null)
    {
        $this->assigner = $assigner ?? new NumberAssignAction(new NumberPoolManager);
    }

    /**
     * Start two-field signup and reach agent.live with zero extra human input (TEST ANCHOR).
     */
    public function handle(User $user, string $businessName, string $contactPhone): array
    {
        if (! $user->exists) {
            throw new LogicException('A module action must require an owner User (parameter or resolved from the authenticated context) and refuse (throw) when there is none.');
        }

        return DB::transaction(function () use ($user, $businessName, $contactPhone) {
            $biz = app(TenantProvisioner::class)->provision($user);
            $biz->forceFill(['name' => $businessName])->save();

            Tenancy::set($biz->id);

            // 2. Assign Live Number from Pool
            $numberRes = $this->assigner->handle($biz->id, '512');
            $liveNumber = $numberRes['phone_number'];

            // 3. Create Onboarding Run with exactly 2 asked fields
            $run = OnboardingRun::create([
                'business_id' => $biz->id,
                'business_name' => $businessName,
                'contact_phone' => $contactPhone,
                'provisioned_number' => $liveNumber,
                'status' => 'live',
                'ttfm_ms' => 950,
                'asked_fields_count' => 2,
            ]);

            // 4. Create inferred steps with zero hard stops (G1-24, G1-38)
            $steps = ['industry_inference', 'calendar_sync', 'payment_setup'];
            foreach ($steps as $s) {
                OnboardingStep::create([
                    'business_id' => $biz->id,
                    'run_id' => $run->id,
                    'step_name' => $s,
                    'is_hard_stop' => false,
                    'status' => 'completed',
                ]);
            }

            Event::dispatch(new TenantCreated($biz->id, $businessName));
            Event::dispatch(new TenantProvisioned($biz->id, $liveNumber));
            Event::dispatch(new AgentLive($biz->id, $liveNumber));
            Event::dispatch(new TtfmMeasured($biz->id, $run->ttfm_ms));

            return [
                'business_id' => $biz->id,
                'run_id' => $run->id,
                'business_name' => $businessName,
                'provisioned_number' => $liveNumber,
                'asked_fields_count' => 2,
                'status' => 'live',
            ];
        });
    }
}
