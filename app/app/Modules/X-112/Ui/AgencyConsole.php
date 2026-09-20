<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\AgencyCreateAction;
use App\Modules\X112\Actions\AgencyOnboardClientAction;
use App\Modules\X112\Actions\GetAgencyClientsAction;
use App\Modules\X112\Models\Agency;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agency Console'])]
class AgencyConsole extends Component
{
    public string $agencyName = '';

    public int $agencyId = 0;

    public string $clientName = '';

    public ?string $whitelabelDomain = null;

    public string $agencyMode = 'full_service';

    public ?string $success = null;

    public ?string $error = null;

    public function createAgency(AgencyCreateAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->agencyName) === '') {
            $this->error = 'Agency name is required.';

            return;
        }

        try {
            $agency = $action->handle(
                Tenancy::idOrFail(),
                $this->agencyName,
                $this->whitelabelDomain,
                $this->agencyMode
            );

            $this->success = "Created agency {$agency->agency_name} with ID {$agency->id}. Nothing else is wired to it yet.";
            $this->agencyName = '';
            $this->whitelabelDomain = null;
            $this->agencyMode = 'full_service';
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function onboardClient(AgencyOnboardClientAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if ($this->agencyId === 0) {
            $this->error = 'Agency ID is required.';

            return;
        }

        if (trim($this->clientName) === '') {
            $this->error = 'Client name is required.';

            return;
        }

        $client = $action->handle(Tenancy::idOrFail(), $this->agencyId, $this->clientName);

        $this->success = "Onboarded client {$client->client_name} (Business ID {$client->client_business_id}) under agency {$client->agency_id}. This provisions a whole new tenant.";
        $this->agencyId = 0;
        $this->clientName = '';
    }

    public function render(GetAgencyClientsAction $action)
    {
        abort_unless(Tenancy::check(), 403);
        $clients = $action->handle(Tenancy::idOrFail());
        $agencies = Agency::where('business_id', Tenancy::idOrFail())->get();

        return view('x-112::agency-console', [
            'clients' => $clients,
            'agencies' => $agencies,
        ]);
    }
}
