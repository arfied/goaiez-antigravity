<?php

namespace App\Modules\X192\Ui;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout')]
class MembershipsList extends Component
{
    public function mount(): void {}

    public function render()
    {
        $memberships = DB::table('directory_memberships')
            ->orderBy('is_noindex', 'asc') // noindex at the bottom
            ->orderBy('directory_index', 'desc')
            ->get();

        return view('x-192::memberships_list', compact('memberships'));
    }
}
