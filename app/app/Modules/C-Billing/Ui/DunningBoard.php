<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Models\DunningAttempt;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Dunning board'])]
class DunningBoard extends Component
{
    /**
     * The words an owner reads for each outcome, and the signal each carries.
     *
     * Keyed by the enum's own backed value so an unmapped case renders as
     * 'unknown' rather than leaking a raw token onto the page.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const OUTCOMES = [
        'recovered' => ['Recovered on retry', 'ok'],
        'declined' => ['Declined again', 'attention'],
        'unreachable' => ['Could not reach the gateway', 'unknown'],
        'exhausted' => ['Suspended, out of attempts', 'alert'],
        'resolved_by_tenant' => ['Fixed by the customer', 'ok'],
        'canceled_at_gateway' => ['Canceled at the gateway', 'unknown'],
    ];

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $attempts = DunningAttempt::where('business_id', Tenancy::id())
            ->orderByDesc('sequence')
            ->orderByDesc('attempt')
            ->get();

        return view('c-billing::dunning-board', [
            'attempts' => $attempts,
            'outcomes' => self::OUTCOMES,
        ]);
    }
}
