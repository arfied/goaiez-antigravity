<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Modules\X182\Models\SocialPost;
use Livewire\Component;

class SocialQueue extends Component
{
    public int $businessId = 0;

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
