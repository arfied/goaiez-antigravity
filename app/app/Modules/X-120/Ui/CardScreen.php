<?php

declare(strict_types=1);

namespace App\Modules\X120\Ui;

use App\Modules\X120\Actions\CardRotateAction;
use App\Modules\X120\Models\CardToken;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Component;

class CardScreen extends Component
{
    public ?string $status = null;

    public function makeDefault(int $cardId, CardRotateAction $action): void
    {
        $action->rotateDefault(Tenancy::idOrFail(), $cardId);
    }

    public function addCard(): void
    {
        $this->status = 'waiting on Stripe tokenisation';
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $cards = CardToken::where('business_id', Tenancy::idOrFail())->get();
        $now = now();

        $expiringCards = $cards->filter(function ($card) use ($now) {
            $expDate = Carbon::createFromDate($card->exp_year, $card->exp_month, 1)->endOfMonth();

            return $expDate->isPast() || $expDate->diffInDays($now) <= 30;
        });

        return view('x-120::card-screen', [
            'cards' => $cards,
            'expiringCards' => $expiringCards,
        ]);
    }
}
