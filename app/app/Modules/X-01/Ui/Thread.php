<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Modules\CAgent\Actions\AgentDraftAction;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Message;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Livewire\Component;

class Thread extends Component
{
    public Person $person;

    public ?string $draftReply = null;
    public ?int $draftForMessageId = null;
    public string $replyText = '';
    public ?string $errorMessage = null;

    public function mount(Person $person)
    {
        $this->person = $person;
    }

    public function draftAiReply(int $messageId, AgentDraftAction $draftAction)
    {
        $this->errorMessage = null;
        try {
            $message = Message::findOrFail($messageId);
            $this->draftForMessageId = $messageId;
            $this->draftReply = $draftAction->handle(Tenancy::idOrFail(), (string) $message->body);
            $this->replyText = $this->draftReply;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not draft reply: ' . $e->getMessage();
        }
    }

    public function sendReply(UnifiedInboxManager $manager)
    {
        $this->validate([
            'replyText' => 'required|string|min:1'
        ]);

        $this->errorMessage = null;
        try {
            $conversation = Conversation::where('person_id', $this->person->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $conversation) {
                return;
            }

            $operatorId = Tenancy::userId() ?? 1;
            $operatorName = 'Operator';

            $manager->takeover(Tenancy::idOrFail(), $conversation->id, $operatorId, $operatorName);
            $replyData = $manager->replyWithTakeover(Tenancy::idOrFail(), $conversation->id, $this->replyText);

            Message::create([
                'business_id' => Tenancy::idOrFail(),
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'operator',
                'sender_id' => $operatorId,
                'body' => $replyData['formatted_reply'],
                'channel' => $conversation->channel ?? 'sms',
            ]);

            $this->replyText = '';
            $this->draftReply = null;
            $this->draftForMessageId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not send reply: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $conversationIds = Conversation::where('person_id', $this->person->id)->pluck('id');

        $messages = Message::whereIn('conversation_id', $conversationIds)
            ->orderBy('created_at', 'asc')
            ->get();

        $isSample = false;
        if ($this->person->name === 'SAMPLE PERSON' || str_contains((string)$this->person->name, 'SAMPLE')) {
            $isSample = true;
        }

        return view('x-01::thread', [
            'messages' => $messages,
            'isSample' => $isSample,
        ]);
    }
}
