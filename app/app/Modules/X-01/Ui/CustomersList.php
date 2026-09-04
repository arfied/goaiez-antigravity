<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Modules\X121\Models\Person;
use App\Modules\X121\Models\Conversation;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Actions\ConversationReadAction;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class CustomersList extends Component
{
    use WithPagination;

    #[Locked]
    public int $businessId = 0;
    
    public bool $isSample = false;
    public bool $failed = false;

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
                $persons = Person::where('business_id', $this->businessId)
                    ->orderBy('id', 'desc')
                    ->paginate(15);
                    
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
