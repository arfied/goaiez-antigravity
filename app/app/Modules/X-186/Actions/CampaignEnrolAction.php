<?php

declare(strict_types=1);

namespace App\Modules\X186\Actions;

use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X186\Models\CampaignStep;

final class CampaignEnrolAction
{
    public function __construct(private readonly EntityReadAction $people) {}

    /**
     * Enrols one person into one campaign at step 1 and sends nothing. The first send stays
     * CampaignRunAction's, which finds this run by the same business, campaign and person.
     *
     * Refuses a campaign with no steps in this business, an id that is not a row in this
     * business's people (campaign_runs.person_id holds a people id, see CampaignRun), and a
     * second enrolment of the same person into the same campaign, whatever state the first
     * run is in.
     */
    public function enrol(int $businessId, string $campaignId, int $personId): CampaignRun
    {
        $hasSteps = CampaignStep::where('business_id', $businessId)
            ->where('campaign_id', $campaignId)
            ->exists();

        if (! $hasSteps) {
            throw new \DomainException("Campaign {$campaignId} has no steps, so nobody can be enrolled in it.");
        }

        if ($this->people->handle('people', $personId, $businessId) === null) {
            throw new \DomainException("Person {$personId} is not a person of this business.");
        }

        $alreadyEnrolled = CampaignRun::where('business_id', $businessId)
            ->where('campaign_id', $campaignId)
            ->where('person_id', $personId)
            ->exists();

        if ($alreadyEnrolled) {
            throw new \DomainException("Person {$personId} is already enrolled in campaign {$campaignId}.");
        }

        return CampaignRun::create([
            'business_id' => $businessId,
            'campaign_id' => $campaignId,
            'person_id' => $personId,
            'current_step' => 1,
            'is_active' => true,
            'is_suppressed' => false,
        ]);
    }
}
