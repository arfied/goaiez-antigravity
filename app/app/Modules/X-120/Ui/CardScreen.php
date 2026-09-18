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
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Payment methods'])]
class CardScreen extends Component
{
    public bool $adding = false;

    public string $number = '';

    public string $expMonth = '';

    public string $expYear = '';

    public string $name = '';

    public ?string $waiting = null;

    public ?string $errorHeading = null;

    public ?string $error = null;

    public ?string $success = null;

    public function makeDefault(int $cardId, CardRotateAction $action): void
    {
        $this->forgetCardFields();
        $this->errorHeading = null;
        $this->error = null;
        $this->success = null;
        $this->waiting = null;

        try {
            $card = CardToken::where('business_id', Tenancy::idOrFail())->findOrFail($cardId);
            $action->rotateDefault(Tenancy::idOrFail(), $card->id);
            $this->success = 'Card set as default.';
        } catch (ModelNotFoundException) {
            $this->errorHeading = 'Could not set the default card';
            $this->error = 'Card not found in this account.';
        }
    }

    public function addCard(): void
    {
        $this->forgetCardFields();
        $this->adding = true;
        $this->waiting = null;
        $this->errorHeading = null;
        $this->error = null;
        $this->success = null;
    }

    public function present(CardPresentAction $action): void
    {
        $this->errorHeading = null;
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
            $this->errorHeading = 'Could not check that card';
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

    /**
     * Cardholder data lives in public properties, and Livewire renders those into the page as
     * wire:snapshot on every response. So the number, the expiry and the name are forgotten as a
     * set at the boundary of every action this screen exposes, not only the one that checks them:
     * otherwise a number typed here and left behind by an unrelated click is echoed back into the
     * page for the rest of the session, and the form's promise that the number reaches this app
     * once is false. P-196 names all three.
     */
    private function forgetCardFields(): void
    {
        $this->number = '';
        $this->expMonth = '';
        $this->expYear = '';
        $this->name = '';
    }
}
