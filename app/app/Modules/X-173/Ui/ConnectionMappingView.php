<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Actions\AccountingMapAction;
use App\Modules\X173\Models\AccountMapping;
use App\Modules\X173\Models\AccountingConnection;
use App\Support\Tenancy;
use Livewire\Component;

class ConnectionMappingView extends Component
{
    public string $internalCategory = '';
    public string $remoteGlAccountId = '';
    public string $remoteGlAccountName = '';
    public string $message = '';
    public string $oauthMessage = '';

    public function mapAccount(int $connectionId, AccountingMapAction $action)
    {
        $result = $action->mapAccount(Tenancy::idOrFail(), $connectionId, $this->internalCategory, $this->remoteGlAccountId, $this->remoteGlAccountName);
        if ($result['status'] === 'refused') {
            $this->message = $result['message'];
        } else {
            $this->message = 'mapped';
        }
    }

    public function connect(string $provider)
    {
        $this->oauthMessage = 'Waiting on ' . $provider . ' OAuth';
    }

    public function render()
    {
        $mappings = AccountMapping::where('business_id', Tenancy::idOrFail())->get();
        $connection = AccountingConnection::where('business_id', Tenancy::idOrFail())->first();

        return view('x-173::connection-mapping', [
            'mappings' => $mappings,
            'connection' => $connection,
        ]);
    }
}
