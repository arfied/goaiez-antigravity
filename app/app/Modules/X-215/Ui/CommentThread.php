<?php

declare(strict_types=1);

namespace App\Modules\X215\Ui;

use App\Modules\X215\Models\DocumentComment;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CommentThread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $comments = ($this->businessId > 0)
            ? DocumentComment::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-215::comment-thread', [
            'comments' => $comments,
        ]);
    }
}
