<?php

declare(strict_types=1);

namespace App\Modules\X144\Ui;

use App\Modules\X144\Models\VisibilityQuery;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuestionList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $queries = ($this->businessId > 0)
            ? VisibilityQuery::where('business_id', $this->businessId)->with('answers')->get()
            : collect();

        return view('x-144::question-list', [
            'queries' => $queries,
        ]);
    }
}
