<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Actions\AccountingMapAction;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountMapping;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class ConnectionMappingView extends Component
{
    public string $provider = 'quickbooks';

    public string $realmId = '';

    public array $map = [];

    public ?string $error = null;

    public ?string $success = null;

    public ?string $waiting = null;

    public function connect(): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = sprintf('Waiting on %s OAuth: no %s credentials exist in this checkout, so nothing was connected. The one-click connect lands when the owner grants them.', $this->provider, $this->provider);
    }

    public function mapAccount(int $connectionId, AccountingMapAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = null;

        $category = $this->map[$connectionId]['category'] ?? '';
        $glId = $this->map[$connectionId]['glId'] ?? '';
        $glName = $this->map[$connectionId]['glName'] ?? '';

        try {
            $r = $action->mapAccount(Tenancy::idOrFail(), $connectionId, $category, $glId, $glName);
            if ($r['status'] === 'refused') {
                $this->error = $r['message'];
            } else {
                $this->success = $r['message'];
                unset($this->map[$connectionId]);
            }
        } catch (ModelNotFoundException $e) {
            $this->error = "That ledger connection isn't in this account.";
        } catch (\Throwable $e) {
            $this->error = 'We could not save that mapping: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $connections = AccountingConnection::where('business_id', $businessId)->orderBy('id')->get();
        $mappings = AccountMapping::where('business_id', $businessId)->orderBy('id')->get()->groupBy('connection_id');

        return view('x-173::connection-mapping', [
            'connections' => $connections,
            'mappings' => $mappings,
        ]);
    }
}
