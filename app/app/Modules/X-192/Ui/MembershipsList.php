<?php

namespace App\Modules\X192\Ui;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class MembershipsList extends Component
{
    public function render()
    {
        $memberships = DB::table('directory_memberships')
            ->orderBy('is_noindex', 'asc') // noindex at the bottom
            ->orderBy('directory_index', 'desc')
            ->get();
        return view('x-192::memberships_list', compact('memberships'));
    }
}
