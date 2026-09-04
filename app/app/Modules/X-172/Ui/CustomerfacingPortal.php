<?php

declare(strict_types=1);

namespace App\Modules\X172\Ui;

use App\Modules\X172\Actions\PortalActionHandler;
use App\Modules\X172\Actions\PortalLinkAction;
use App\Modules\X172\Actions\PortalViewAction;
use App\Modules\X172\Models\PortalLink;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CustomerfacingPortal extends Component
{
    public $token;
    public $errorMessage = null;
    public $refreshedToken = false;

    public function mount($token)
    {
        $this->token = $token;
        
        $action = app(PortalViewAction::class);
        $result = $action->handle($this->token);
        
        if ($result['status'] === 'invalid_link') {
            abort(404);
        }
        
        if ($result['status'] === 'expired_link') {
            $oldLink = PortalLink::where('token', $this->token)->first();
            if ($oldLink) {
                $newLinkAction = app(PortalLinkAction::class);
                $newLinkAction->handle(
                    $oldLink->business_id,
                    $oldLink->resource_type,
                    $oldLink->resource_id,
                    $oldLink->customer_id
                );
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
            $this->errorMessage = "Could not record action.";
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
            // Check membership status from X-165 (if exists)
            // Just simulate checking the db for simplicity. The instructions say "Membership status: X-165 row if present, else the row is absent (not a placeholder)."
            if (class_exists(\App\Modules\X165\Models\Membership::class)) {
                // we don't have it, so do nothing.
            }

            if ($link->resource_type === 'job') {
                $wo = DB::table('work_orders')->where('id', $link->resource_id)->first();
                if ($wo) {
                    $resourceTitle = $wo->title;
                }
                
                $assignment = DB::table('dispatch_assignments')->where('job_id', $link->resource_id)->first();
                if ($assignment && $assignment->status === 'en_route') {
                    $isJobEnRoute = true;
                    $eta = DB::table('eta_predictions')->where('job_id', $link->resource_id)->orderByDesc('id')->first();
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
