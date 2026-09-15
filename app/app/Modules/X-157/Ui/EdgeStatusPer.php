<?php

declare(strict_types=1);

namespace App\Modules\X157\Ui;

use App\Modules\X157\Actions\EdgeRollbackAction;
use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Edge deployments'])]
class EdgeStatusPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $errorMessage = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function rollback(int $deploymentId)
    {
        $this->errorMessage = null;
        try {
            app(EdgeRollbackAction::class)->handle($this->businessId, $deploymentId);
        } catch (ModelNotFoundException $e) {
            $this->errorMessage = 'That deployment is not available for this business.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $deployments = ($this->businessId > 0)
            ? Deployment::with('edgeZone')
                ->where('business_id', $this->businessId)
                ->orderByDesc('deployed_at')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('x-157::edge-status-per', [
            'deployments' => $deployments,
        ]);
    }
}
