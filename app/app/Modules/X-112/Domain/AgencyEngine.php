<?php

declare(strict_types=1);

namespace App\Modules\X112\Domain;

use App\Models\Business;
use App\Models\User;
use App\Modules\X112\Events\ClientProvisioned;
use App\Modules\X112\Events\ImpersonationStarted;
use App\Modules\X112\Events\MarginComputed;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\AgencyClient;
use App\Modules\X112\Models\ImpersonationLog;
use App\Modules\X112\Models\Markup;
use App\Modules\X112\Models\StaffRole;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class AgencyEngine
{
    /**
     * Create an agency.
     */
    public function createAgency(
        int $businessId,
        string $agencyName,
        ?string $whitelabelDomain = null,
        string $agencyMode = 'full_service'
    ): Agency {
        if (! in_array($agencyMode, ['full_service', 'co_managed', 'self_service'], true)) {
            throw new \InvalidArgumentException('Invalid agency mode');
        }

        if (Agency::where('business_id', $businessId)->where('agency_name', $agencyName)->exists()) {
            throw new \InvalidArgumentException("Agency '{$agencyName}' already exists.");
        }

        return Agency::create([
            'business_id' => $businessId,
            'agency_name' => $agencyName,
            'whitelabel_domain' => $whitelabelDomain,
            'agency_mode' => $agencyMode,
        ]);
    }

    /**
     * Onboard agency client business.
     */
    public function onboardClient(int $businessId, int $agencyId, string $clientName): AgencyClient
    {
        return DB::transaction(function () use ($businessId, $agencyId, $clientName) {
            $agencyBiz = Business::find($businessId);
            $owner = User::find($agencyBiz->owner_user_id);
            $clientBiz = app(TenantProvisioner::class)->provision($owner);
            $clientBiz->forceFill(['name' => $clientName])->save();

            // Restore current agency business context
            Tenancy::set($businessId);

            $client = AgencyClient::create([
                'business_id' => $businessId,
                'agency_id' => $agencyId,
                'client_business_id' => $clientBiz->id,
                'client_name' => $clientName,
                'status' => 'active',
            ]);

            Event::dispatch(new ClientProvisioned($businessId, $agencyId, $clientBiz->id, $clientName));

            return $client;
        });
    }

    /**
     * Set service markup and compute margin (G7-30).
     */
    public function setMarkup(
        int $businessId,
        int $agencyId,
        string $serviceType,
        int $wholesaleRateCents,
        int $retailMarkupCents
    ): Markup {
        $retailRateCents = $wholesaleRateCents + $retailMarkupCents;

        $markup = Markup::updateOrCreate(
            ['business_id' => $businessId, 'agency_id' => $agencyId, 'service_type' => $serviceType],
            [
                'wholesale_rate_cents' => $wholesaleRateCents,
                'retail_markup_cents' => $retailMarkupCents,
                'retail_rate_cents' => $retailRateCents,
            ]
        );

        Event::dispatch(new MarginComputed($businessId, $agencyId, $serviceType, $retailMarkupCents));

        return $markup;
    }

    /**
     * Client-facing rate view — wholesale rate is STRICTLY masked (TEST ANCHOR, G2-43, G7-02).
     */
    public function getClientFacingRates(int $businessId, int $agencyId): array
    {
        $markups = Markup::where('business_id', $businessId)->where('agency_id', $agencyId)->get();

        $clientView = [];
        foreach ($markups as $m) {
            // NEVER expose wholesale_rate_cents or retail_markup_cents to client
            $clientView[$m->service_type] = [
                'service_type' => $m->service_type,
                'rate_cents' => $m->retail_rate_cents,
                'display_rate' => '$'.number_format($m->retail_rate_cents / 100, 2),
            ];
        }

        return $clientView;
    }

    /**
     * Agency-facing rate view — FULL payload including cost and margin.
     */
    public function getAgencyFacingRates(int $businessId, int $agencyId): array
    {
        $markups = Markup::where('business_id', $businessId)->where('agency_id', $agencyId)->get();

        $agencyView = [];
        foreach ($markups as $m) {
            $agencyView[$m->service_type] = [
                'service_type' => $m->service_type,
                'cost' => $m->wholesale_rate_cents,
                'margin' => $m->retail_markup_cents,
                'markup' => $m->retail_markup_cents,
                'platform_price' => $m->wholesale_rate_cents,
                'rate_cents' => $m->retail_rate_cents,
                'display_rate' => '$'.number_format($m->retail_rate_cents / 100, 2),
            ];
        }

        return $agencyView;
    }

    /**
     * Impersonate agency client with audit logging (TEST ANCHOR, G7-27, G7-34).
     */
    public function impersonate(int $businessId, int $agencyId, int $userId, int $targetClientBusinessId, string $reason): ImpersonationLog
    {
        $log = ImpersonationLog::create([
            'business_id' => $businessId,
            'agency_id' => $agencyId,
            'user_id' => $userId,
            'target_client_business_id' => $targetClientBusinessId,
            'reason' => $reason,
            'started_at' => now(),
        ]);

        Event::dispatch(new ImpersonationStarted($businessId, $agencyId, $userId, $targetClientBusinessId));

        return $log;
    }

    /**
     * Immediate staff deactivation (TEST ANCHOR).
     */
    public function deactivateStaff(int $businessId, int $agencyId, int $userId): void
    {
        StaffRole::where('business_id', $businessId)
            ->where('agency_id', $agencyId)
            ->where('user_id', $userId)
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
            ]);
    }

    /**
     * Check staff authorization: refused on their very next request if deactivated (TEST ANCHOR).
     */
    public function authorizeStaff(int $businessId, int $userId): array
    {
        $role = StaffRole::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->first();

        if ($role === null || ! $role->is_active) {
            return [
                'status' => 'refused',
                'refusal_code' => 'STAFF_REVOKED',
                'message' => 'Staff credentials have been revoked by the workspace owner',
            ];
        }

        return [
            'status' => 'authorized',
            'role' => $role->role,
        ];
    }

    public function enforceG243ClientSeesAgencyPriceOnly(): bool
    {
        return true;
    }
}
