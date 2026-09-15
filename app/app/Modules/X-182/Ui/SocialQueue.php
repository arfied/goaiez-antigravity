<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Modules\X182\Models\SocialPost;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Social queue'])]
class SocialQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
    }

    public function render()
    {
        $posts = ($this->businessId > 0)
            ? SocialPost::where('business_id', $this->businessId)->with('comments')->get()
            : collect();

        return view('x-182::social-queue', [
            'posts' => $posts,
        ]);
    }
}
