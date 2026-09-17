<?php

declare(strict_types=1);

namespace App\Modules\X119\Ui;

use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'What we learned'])]
class ReviewwhatifoundScreen extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();
        $facts = DB::table('facts')->where('business_id', $businessId)->where('is_valid', false)->orderByDesc('id')->get();

        return view('x-119::reviewwhatifound-screen', ['facts' => $facts]);
    }
}
