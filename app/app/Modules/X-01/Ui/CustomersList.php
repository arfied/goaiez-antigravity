<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Models\Conversation;
use App\Modules\X01\Actions\ConversationReadAction;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Customers'])]
class CustomersList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public bool $failed = false;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function openPerson(int $personId)
    {
        $this->dispatch('openPerson', $personId);
    }

    public function readConversation(int $conversationId, ConversationReadAction $action)
    {
        $convo = $action->handle($this->businessId, $conversationId);
    }

    public function render()
    {
        $persons = collect();
        $leadScores = collect();
        $latestConversations = collect();

        try {
            $this->failed = false;
            if ($this->businessId > 0) {
                $personsList = app(PersonLookupAction::class)->listForBusiness($this->businessId);
                $persons = collect($personsList);

                $personIds = $persons->pluck('id');
                $leadScores = LeadScore::whereIn('person_id', $personIds)->get()->keyBy('person_id');
                $latestConversations = Conversation::whereIn('person_id', $personIds)->latest('id')->get()->keyBy('person_id');
            }
        } catch (Throwable $e) {
            $this->failed = true;
        }

        return view('x-01::customers-list', [
            'persons' => $persons,
            'leadScores' => $leadScores,
            'latestConversations' => $latestConversations,
        ]);
    }
}
