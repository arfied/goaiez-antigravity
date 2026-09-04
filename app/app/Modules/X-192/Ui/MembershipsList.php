<?php

namespace App\Modules\X192\Ui;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout')]
class MembershipsList extends Component
{
    public function mount(): void {
        abort_unless(auth()->check() && auth()->user()->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    }

    public function render()
    {
        $memberships = DB::table('directory_memberships')
            ->orderBy('is_noindex', 'asc') // noindex at the bottom
            ->orderBy('directory_index', 'desc')
            ->get();

        return view('x-192::memberships_list', compact('memberships'));
    }
}
