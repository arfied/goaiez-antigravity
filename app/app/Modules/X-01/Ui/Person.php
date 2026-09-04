<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Modules\X121\Models\Person as PersonModel;
use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Message;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\ContactTag;
use App\Modules\X01\Models\TakeoverLatch;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class Person extends Component
{
    #[Locked]
    public int $personId = 0;
    
    public bool $failed = false;

    public function render()
    {
        $person = null;
        $score = null;
        $tags = [];
        $messages = collect();
        $conversations = collect();
        $hasTakeover = false;

        try {
            $this->failed = false;
            
            if ($this->personId > 0) {
                $person = PersonModel::find($this->personId);
                
                if ($person) {
                    $score = LeadScore::where('person_id', $this->personId)->first();
                    $tags = ContactTag::where('contact_id', $this->personId)->pluck('tag')->all();
                    
                    $conversations = Conversation::where('person_id', $this->personId)->get();
                    $convoIds = $conversations->pluck('id');
                    
                    if ($convoIds->isNotEmpty()) {
                        $messages = Message::whereIn('conversation_id', $convoIds)
                            ->latest('created_at')
                            ->latest('id')
                            ->get();
                            
                        $hasTakeover = TakeoverLatch::whereIn('conversation_id', $convoIds)
                            ->where('is_active', true)
                            ->exists();
                    }
                }
            }
        } catch (Throwable $e) {
            $this->failed = true;
        }

        return view('x-01::person', [
            'person' => $person,
            'score' => $score,
            'tags' => $tags,
            'messages' => $messages,
            'conversations' => $conversations->keyBy('id'),
            'hasTakeover' => $hasTakeover,
        ]);
    }
}
