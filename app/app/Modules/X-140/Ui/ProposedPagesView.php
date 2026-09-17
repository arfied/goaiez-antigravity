<?php

declare(strict_types=1);

namespace App\Modules\X140\Ui;

use App\Enums\UserRole;
use App\Modules\X140\Models\ContentTopic;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Proposed pages'])]
class ProposedPagesView extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $topics = ($this->businessId > 0)
            ? ContentTopic::where('business_id', $this->businessId)->with('sources')->orderByDesc('id')->get()
            : collect();

        return view('x-140::proposed-pages', [
            'topics' => $topics,
        ]);
    }
}
