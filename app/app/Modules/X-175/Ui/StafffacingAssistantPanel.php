<?php

declare(strict_types=1);

namespace App\Modules\X175\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Actions\PriceLookupAction;
use App\Modules\X175\Actions\FieldAskAction;
use App\Modules\X175\Models\FieldSuggestion;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StafffacingAssistantPanel extends Component
{
    #[Locked]
    public int $businessId;

    public string $question = '';

    public ?string $lastAnswer = null;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Staff) || auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function ask()
    {
        $q = trim($this->question);
        if ($q === '') {
            return;
        }

        $lookup = app(PriceLookupAction::class)->handle($this->businessId, $q, 'staff');

        if ($lookup['status'] === 'quoted' && ! empty($lookup['is_sample'])) {
            $isSamplePrice = true;
            $verifiedAnswer = null;
        } elseif ($lookup['status'] === 'quoted') {
            $isSamplePrice = false;
            $verifiedAnswer = $lookup['service_name'].' is '.$lookup['formatted_price'].' from the pricebook';
        } else {
            $isSamplePrice = false;
            $verifiedAnswer = 'Not in the pricebook. Nothing to quote.';
        }

        $result = app(FieldAskAction::class)->handle(
            $this->businessId,
            null,
            auth()->id(),
            $q,
            $isSamplePrice,
            $verifiedAnswer
        );

        $this->lastAnswer = $result['response'];
        $this->question = '';
    }

    public function askAgain(int $id)
    {
        $s = FieldSuggestion::where('business_id', $this->businessId)->findOrFail($id);
        $this->question = $s->query_text;
        $this->ask();
    }

    public function render()
    {
        $suggestions = FieldSuggestion::where('business_id', $this->businessId)->orderByDesc('id')->get();
        $pill = [];

        foreach ($suggestions as $s) {
            if ($s->is_unconfirmed_price) {
                $pill[$s->id] = ['attention', 'Needs a price'];
            } elseif ($s->is_upsell) {
                $pill[$s->id] = ['ok', 'Upsell'];
            } else {
                $pill[$s->id] = ['ok', 'Answered'];
            }
        }

        return view('x-175::stafffacing-assistant-panel', [
            'suggestions' => $suggestions,
            'pill' => $pill,
        ]);
    }
}
