<?php

declare(strict_types=1);

namespace App\Modules\X120\Ui;

use App\Modules\X120\Actions\CardPresentAction;
use App\Modules\X120\Actions\CardRotateAction;
use App\Modules\X120\Domain\CardExpiredException;
use App\Modules\X120\Domain\CardNumberInvalidException;
use App\Modules\X120\Models\CardToken;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class CardScreen extends Component
{
    public bool $adding = false;

    public string $number = '';

    public string $expMonth = '';

    public string $expYear = '';

    public string $name = '';

    public ?string $waiting = null;

    public ?string $error = null;

    public ?string $success = null;

    public function makeDefault(int $cardId, CardRotateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        try {
            $card = CardToken::where('business_id', Tenancy::idOrFail())->findOrFail($cardId);
            $action->rotateDefault(Tenancy::idOrFail(), $card->id);
            $this->success = 'Card set as default.';
        } catch (ModelNotFoundException) {
            $this->error = 'Card not found in this account.';
        }
    }

    public function addCard(): void
    {
        $this->adding = true;
        $this->waiting = null;
        $this->error = null;
        $this->success = null;
    }

    public function present(CardPresentAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = null;

        try {
            $card = $action->handle($this->number, (int) $this->expMonth, (int) $this->expYear, $this->name);
            $this->waiting = sprintf(
                'Waiting on Stripe tokenisation: the %s ending %s (%02d/%d, %s) is not stored until Stripe returns a token; the number was not kept.',
                $card['brand'],
                $card['last_four'],
                $card['exp_month'],
                $card['exp_year'],
                $card['name']
            );
        } catch (CardExpiredException|CardNumberInvalidException $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->forgetCardFields();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $cards = CardToken::where('business_id', Tenancy::idOrFail())->orderBy('id')->get();
        $now = now();

        $expiringCards = $cards->filter(function ($card) use ($now) {
            $expDate = Carbon::createFromDate($card->exp_year, $card->exp_month, 1)->endOfMonth();

            return $expDate->isPast() || $now->diffInDays($expDate, false) <= 30;
        });

        return view('x-120::card-screen', [
            'cards' => $cards,
            'expiringCards' => $expiringCards,
        ]);
    }

    public function forgetCardFields(): void
    {
        $this->number = '';
        $this->expMonth = '';
        $this->expYear = '';
        $this->name = '';
    }
}
