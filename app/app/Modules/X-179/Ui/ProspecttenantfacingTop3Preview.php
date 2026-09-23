<?php

declare(strict_types=1);

namespace App\Modules\X179\Ui;

use App\Enums\UserRole;
use App\Modules\X179\Models\TemplateMatch;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Top 3 Matched Template Previews'])]
class ProspecttenantfacingTop3Preview extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public int $prospectId = 0;

    public function mount(int $prospectId)
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);

        $businessId = Tenancy::idOrFail();

        $exists = TemplateMatch::where('business_id', $businessId)
            ->where('prospect_id', $prospectId)
            ->exists();
        if (! $exists) {
            abort(404);
        }

        $this->businessId = (int) $businessId;
        $this->prospectId = $prospectId;
    }

    public function render()
    {
        $previews = ($this->businessId > 0 && $this->prospectId > 0)
            ? TemplateMatch::where('business_id', $this->businessId)->where('prospect_id', $this->prospectId)->take(3)->get()
            : collect();

        return view('x-179::prospecttenantfacing-top3-preview', [
            'previews' => $previews,
        ]);
    }
}
