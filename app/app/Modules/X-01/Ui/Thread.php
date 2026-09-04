<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Models\Customer;
use App\Modules\CAgent\Actions\AgentDraftAction;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X121\Models\Conversation;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Thread extends Component
{
    public Customer $customer;

    public ?string $draftReply = null;

    public ?int $draftForMessageId = null;

    public string $replyText = '';

    public ?string $errorMessage = null;

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function draftAiReply(int $messageId, AgentDraftAction $draftAction)
    {
        $this->errorMessage = null;
        try {
            $message = DB::table('messages')->where('id', $messageId)->first();
            if (! $message) {
                return;
            }
            $this->draftForMessageId = $messageId;
            $this->draftReply = $draftAction->handle(Tenancy::idOrFail(), (string) $message->body);
            $this->replyText = $this->draftReply;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not draft reply: '.$e->getMessage();
        }
    }

    public function sendReply(UnifiedInboxManager $manager)
    {
        $this->validate([
            'replyText' => 'required|string|min:1',
        ]);

        $this->errorMessage = null;
        try {
            $conversation = Conversation::where('customer_id', $this->customer->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $conversation) {
                return;
            }

            $operatorId = Tenancy::userId();
            if (! $operatorId) {
                throw new \RuntimeException('Operator not authenticated');
            }

            $operatorName = 'Operator';

            $manager->takeover(Tenancy::idOrFail(), $conversation->id, $operatorId, $operatorName);
            $replyData = $manager->replyWithTakeover(Tenancy::idOrFail(), $conversation->id, $this->replyText);

            DB::table('messages')->insert([
                'business_id' => Tenancy::idOrFail(),
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'operator',
                'sender_id' => (string) $operatorId,
                'body' => $replyData['formatted_reply'],
                'created_at' => now(),
            ]);

            $this->replyText = '';
            $this->draftReply = null;
            $this->draftForMessageId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not send reply: '.$e->getMessage();
        }
    }

    public function render()
    {
        $conversationIds = Conversation::where('customer_id', $this->customer->id)->pluck('id');

        $messages = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->whereIn('messages.conversation_id', $conversationIds)
            ->select('messages.*', 'conversations.channel')
            ->orderBy('messages.created_at', 'asc')
            ->get();

        return view('x-01::thread', [
            'messages' => $messages,
        ]);
    }
}
