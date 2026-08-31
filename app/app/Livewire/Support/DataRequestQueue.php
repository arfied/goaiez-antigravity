<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Enums\DataRequestKind;
use App\Enums\DataRequestStatus;
use App\Models\User;
use App\Services\Support\DataRequests;
use App\Support\Admin\LifecycleAccess;
use App\Support\Admin\SupportAccess;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Ops → Support → Data requests — `28` §9.5's GDPR/CCPA queue.
 *
 * Lists open items by due date, lets ops_admin/super_admin approve erasures
 * (second person) and cancel, and files consent audits / export refusals against
 * a named account. Account 360 remains the place that opens an erasure for the
 * account on screen; this is the queue that clocks them.
 *
 * ⚠️ **NO `Tenancy::actingAs()` AND NO `Business::` HERE** — StaffTest holds the
 * support console to the directory; every write goes through {@see DataRequests}.
 */
final class DataRequestQueue extends Component
{
    public string $businessRef = '';

    public string $customerRef = '';

    public string $detail = '';

    /** 'erasure' | 'hard_offboard' | 'consent_audit' | 'tenant_export' | '' */
    public string $filing = '';

    public ?int $confirmingApproveId = null;

    public ?int $confirmingCancelId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->authorize(SupportAccess::GATE);
    }

    public function startFiling(string $kind): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);

        $this->filing = in_array($kind, ['erasure', 'hard_offboard', 'consent_audit', 'tenant_export'], true)
            ? $kind
            : '';
        $this->resetErrorBag();
    }

    public function cancelFiling(): void
    {
        $this->reset(['filing', 'businessRef', 'customerRef', 'detail']);
        $this->resetErrorBag();
    }

    public function file(DataRequests $queue): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);

        $actor = $this->user();
        $businessId = (int) trim($this->businessRef);

        if ($businessId < 1) {
            $this->addError('businessRef', 'Enter the account number.');

            return;
        }

        try {
            if ($this->filing === 'erasure') {
                $queue->fileErasure($businessId, $actor, $this->detail);
            } elseif ($this->filing === 'hard_offboard') {
                $queue->fileHardOffboard($businessId, $actor, $this->detail);
            } elseif ($this->filing === 'consent_audit') {
                $queue->fileAndFulfillConsentAudit(
                    $businessId,
                    (int) trim($this->customerRef),
                    $actor,
                    $this->detail,
                );
            } elseif ($this->filing === 'tenant_export') {
                $queue->fileTenantExport($businessId, $actor, $this->detail);
            } else {
                throw new InvalidArgumentException('Pick what kind of request to file.');
            }
        } catch (InvalidArgumentException $e) {
            $field = str_contains($e->getMessage(), 'contact') ? 'customerRef' : 'businessRef';
            $this->addError($field, $e->getMessage());

            return;
        }

        // ⚠️ THE EXPORT LINE SAID "recorded as refused — the engine is not
        // built", WHICH STOPPED BEING TRUE WHEN THE ENGINE LANDED (2000).
        // `fileTenantExport()` opens an `Open` row waiting on a second person;
        // a toast telling the agent it was refused is outcome language for an
        // outcome that did not happen, and the agent who believes it never
        // comes back to approve.
        $toast = 'Erasure filed — a second person must approve it';
        if ($this->filing === 'consent_audit') {
            $toast = 'Consent audit produced';
        } elseif ($this->filing === 'tenant_export') {
            $toast = 'Export ask recorded — a second person must approve it';
        }
        Toaster::success($toast);

        $this->cancelFiling();
    }

    public function confirmApprove(int $id): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);
        $this->confirmingApproveId = $id;
        $this->confirmingCancelId = null;
    }

    /**
     * ⚠️ **ROUTES ON THE ROW'S OWN `kind`, RATHER THAN TWO BUTTONS.** Erasure and
     * tenant-export both reuse the same "second person" confirmation UI, so one
     * action name is what stops the template growing a second copy of it —
     * `DataRequests` still refuses either kind attempted through the other's
     * method, which is the layer that actually matters.
     *
     * ⚠️ **`DataRequests::kindOf()` RATHER THAN THIS CLASS NAMING `DataRequest`
     * ITSELF** — `TenancyTest`'s chokepoint holds `data_requests` to the service
     * and the model alone, and this screen is documented there as calling the
     * service and never the table directly.
     */
    public function approve(int $id, DataRequests $queue): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);

        $kind = $queue->kindOf($id);
        if ($kind === null) {
            $this->addError('queue', 'Request not found.');

            return;
        }

        try {
            match ($kind) {
                DataRequestKind::TenantExport => $queue->approveTenantExportById($id, $this->user()),
                DataRequestKind::Erasure => $queue->approveErasureById($id, $this->user()),
                DataRequestKind::ConsentAudit => throw new InvalidArgumentException('Consent audits are fulfilled automatically on filing.'),
            };
        } catch (InvalidArgumentException $e) {
            $this->addError('queue', $e->getMessage());

            return;
        }

        Toaster::success($kind === DataRequestKind::TenantExport
            ? 'Approved — building the download now'
            : 'Cooling window started — account deletes in seven days unless cancelled');
        $this->confirmingApproveId = null;
    }

    public function confirmCancel(int $id): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);
        $this->confirmingCancelId = $id;
        $this->confirmingApproveId = null;
        $this->cancelReason = '';
    }

    public function cancelRequest(int $id, DataRequests $queue): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);

        try {
            $queue->cancelById($id, $this->user(), $this->cancelReason);
        } catch (InvalidArgumentException $e) {
            $this->addError('queue', $e->getMessage());

            return;
        }

        Toaster::success('Request cancelled');
        $this->reset(['confirmingCancelId', 'cancelReason']);
    }

    public function dismissConfirm(): void
    {
        $this->reset(['confirmingApproveId', 'confirmingCancelId', 'cancelReason']);
    }

    public function render(DataRequests $queue): View
    {
        $this->authorize(SupportAccess::GATE);

        return view('livewire.support.data-request-queue', [
            'items' => $queue->open(),
            'mayAct' => auth()->user()?->can(LifecycleAccess::SUSPEND) ?? false,
            'kinds' => DataRequestKind::cases(),
            'awaiting' => DataRequestStatus::AwaitingDeletion,
            'openStatus' => DataRequestStatus::Open,
            'builtStatus' => DataRequestStatus::Built,
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('Not signed in.');
        }

        return $user;
    }
}
