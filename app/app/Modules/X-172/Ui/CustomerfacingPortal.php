<?php

declare(strict_types=1);

namespace App\Modules\X172\Ui;

use App\Modules\X165\Actions\MembershipStatusAction;
use App\Modules\X172\Actions\PortalActionHandler;
use App\Modules\X172\Actions\PortalLinkAction;
use App\Modules\X172\Actions\PortalViewAction;
use App\Modules\X172\Models\PortalLink;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CustomerfacingPortal extends Component
{
    #[Locked]
    public $token;

    public $errorMessage = null;

    public $refreshedToken = false;

    private function ensureTenancyFromToken(): void
    {
        $link = PortalLink::where('token', (string) $this->token)->first();
        if ($link === null) {
            abort(404);
        }
        Tenancy::set((int) $link->business_id);
    }

    public function hydrate(): void
    {
        $this->ensureTenancyFromToken();
    }

    public function mount($token)
    {
        $this->token = $token;
        $this->ensureTenancyFromToken();

        $action = app(PortalViewAction::class);
        $result = $action->handle((string) $this->token);

        if ($result['status'] === 'invalid_link') {
            abort(404);
        }

        if ($result['status'] === 'expired_link') {
            $oldLink = PortalLink::where('token', $this->token)->first();
            if ($oldLink) {
                $newLinkAction = app(PortalLinkAction::class);
                $newLink = $newLinkAction->handle(
                    $oldLink->business_id,
                    $oldLink->resource_type,
                    $oldLink->resource_id,
                    $oldLink->customer_id
                );
                $this->token = $newLink->token;
            }
            $this->refreshedToken = true;
        }
    }

    public function approve()
    {
        $this->handleAction('approved');
    }

    public function requestFollowUp()
    {
        $this->handleAction('follow_up_requested');
    }

    public function requestPay()
    {
        $this->handleAction('pay_requested');
    }

    private function handleAction(string $actionName)
    {
        try {
            $action = app(PortalActionHandler::class);
            $action->handle($this->token, $actionName, []);
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not record action.';
        }
    }

    public function render()
    {
        $link = PortalLink::where('token', $this->token)->first();
        $resourceTitle = null;
        $jobEtaMinutes = null;
        $isJobEnRoute = false;
        $isDocumentPrepared = false;
        $membershipStatus = null;

        if ($link && $link->is_active) {
            if ($link->customer_id) {
                $membershipStatus = app(MembershipStatusAction::class)->handle($link->business_id, $link->customer_id);
            }

            if ($link->resource_type === 'job') {
                $wo = DB::table('work_orders')
                    ->where('business_id', $link->business_id)
                    ->where('id', $link->resource_id)
                    ->first();
                if ($wo) {
                    $resourceTitle = $wo->title;
                }

                $assignment = DB::table('dispatch_assignments')
                    ->where('business_id', $link->business_id)
                    ->where('job_id', $link->resource_id)
                    ->first();
                if ($assignment && $assignment->status === 'en_route') {
                    $isJobEnRoute = true;
                    $eta = DB::table('eta_predictions')
                        ->where('business_id', $link->business_id)
                        ->where('job_id', $link->resource_id)
                        ->orderByDesc('id')
                        ->first();
                    if ($eta) {
                        $jobEtaMinutes = $eta->eta_minutes;
                    }
                }
            } elseif (in_array($link->resource_type, ['estimate', 'invoice'])) {
                $isDocumentPrepared = true;
            }
        }

        return view('x-172::customerfacing-portal', [
            'link' => $link,
            'resourceTitle' => $resourceTitle,
            'isJobEnRoute' => $isJobEnRoute,
            'jobEtaMinutes' => $jobEtaMinutes,
            'isDocumentPrepared' => $isDocumentPrepared,
            'membershipStatus' => $membershipStatus,
        ]);
    }
}
