<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\GetAgencyClientsAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agency Console'])]
class AgencyConsole extends Component
{
    public function render(GetAgencyClientsAction $action)
    {
        abort_unless(Tenancy::check(), 403);
        $clients = $action->handle(Tenancy::idOrFail());

        return view('x-112::agency-console', [
            'clients' => $clients,
        ]);
    }
}
