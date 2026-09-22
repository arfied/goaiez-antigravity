<?php

declare(strict_types=1);

namespace App\Modules\X129\Ui;

use App\Modules\X129\Actions\RedirectsBuildAction;
use App\Modules\X129\Models\RedirectMap;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Redirect queue'])]
class CutoverQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $sourceUrl = '';

    public string $newDomainHost = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function buildRedirect(RedirectsBuildAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->sourceUrl) === '') {
            $this->error = 'Enter the old URL you want redirected.';

            return;
        }

        if (trim($this->newDomainHost) === '') {
            $this->error = 'Enter the new domain host.';

            return;
        }

        $result = $action->build(Tenancy::idOrFail(), [$this->sourceUrl], $this->newDomainHost);
        $map = $result['maps'][0];

        $this->success = 'Redirect mapped: '.$map->source_url.' → '.$map->destination_url.'. It is listed below and counted on the migration card; nothing serves these redirects yet.';

        $this->sourceUrl = '';
        $this->newDomainHost = '';
    }

    public function render()
    {
        $redirects = ($this->businessId > 0)
            ? RedirectMap::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-129::cutover-queue', [
            'redirects' => $redirects,
        ]);
    }
}
