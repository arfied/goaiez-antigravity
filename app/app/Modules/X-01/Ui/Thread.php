<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Enums\MessageSenderType;
use App\Models\Conversation;
use App\Models\Customer;
use App\Modules\CAgent\Actions\AgentDraftAction;
use App\Modules\X01\Actions\ConversationTakeoverReleaseAction;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\TakeoverLatch;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Inbox'])]
class Thread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $notice = null;

    public ?Customer $customer = null;

    public ?string $draftReply = null;

    public ?int $draftForMessageId = null;

    public string $replyText = '';

    public ?string $errorMessage = null;

    public bool $isGhostRisk = false;

    public function mount(?Customer $customer = null)
    {
        if (! $customer && request()->has('customer')) {
            $customer = Customer::find(request()->query('customer'));
        }

        $this->customer = $customer;
        if ($this->customer) {
            $personId = $this->resolvePersonId();
            if ($personId) {
                $score = LeadScore::where('person_id', $personId)->value('grade');
                $this->isGhostRisk = ($score === 'F');
            } else {
                // If there is no Person, we do not fall back to customer.id because people and customers inhabit different ID spaces.
                $this->isGhostRisk = false;
            }
        }
    }

    private function resolvePersonId(): ?int
    {
        if (! $this->customer->email && ! $this->customer->phone) {
            return null;
        }

        $list = app(PersonLookupAction::class)->listForBusiness(
            $this->customer->business_id,
            $this->customer->email,
            $this->customer->phone
        );

        return ! empty($list) ? $list[0]['id'] : null;
    }

    /**
     * Recomputed in render() on every lifecycle because Livewire re-renders after every action.
     * Manual assignments (e.g. during releaseTakeover or sendReply) are redundant and immediately overwritten.
     */
    public bool $hasActiveTakeover = false;

    public function releaseTakeover(ConversationTakeoverReleaseAction $action)
    {
        if (! $this->customer) {
            return;
        }

        $this->errorMessage = null;
        try {
            $personId = $this->resolvePersonId();
            $conversationIds = Conversation::where(function ($q) use ($personId) {
                $q->where('customer_id', $this->customer->id);
                if ($personId) {
                    $q->orWhere('person_id', $personId);
                }
            })->pluck('id');
            $latches = TakeoverLatch::whereIn('conversation_id', $conversationIds)
                ->where('is_active', true)
                ->get();

            if ($latches->isEmpty()) {
                return;
            }

            foreach ($latches as $latch) {
                $action->handle(Tenancy::idOrFail(), $latch->conversation_id);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not release takeover: '.$e->getMessage();
        }
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
        if (! $this->customer) {
            return;
        }

        $this->validate([
            'replyText' => 'required|string|min:1',
        ]);

        $this->errorMessage = null;
        $this->notice = null;
        try {
            $personId = $this->resolvePersonId();
            $conversation = Conversation::where(function ($q) use ($personId) {
                $q->where('customer_id', $this->customer->id);
                if ($personId) {
                    $q->orWhere('person_id', $personId);
                }
            })
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
                'sender_type' => MessageSenderType::Person->value,
                'sender_id' => (string) $operatorId,
                'body' => $replyData['formatted_reply'],
                'created_at' => now(),
            ]);

            $this->replyText = '';
            $this->draftReply = null;
            $this->draftForMessageId = null;
            $this->notice = 'Added to the conversation. Nothing was sent to the customer — to text them, reply from the Inbox.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not add that to the conversation: '.$e->getMessage();
        }
    }

    public function render()
    {
        if ($this->businessId > 0) {
            Tenancy::set($this->businessId);
        }

        $conversations = ($this->businessId > 0 || Tenancy::check())
            ? Conversation::where('business_id', Tenancy::idOrFail())
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->get()
            : collect();

        $messages = collect();
        if ($this->customer) {
            $personId = $this->resolvePersonId();
            $conversationIds = Conversation::where(function ($q) use ($personId) {
                $q->where('customer_id', $this->customer->id);
                if ($personId) {
                    $q->orWhere('person_id', $personId);
                }
            })->pluck('id');

            $messages = DB::table('messages')
                ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
                ->whereIn('messages.conversation_id', $conversationIds)
                ->select('messages.*', 'conversations.channel')
                ->orderBy('messages.created_at', 'asc')
                ->get();

            $this->hasActiveTakeover = TakeoverLatch::whereIn('conversation_id', $conversationIds)
                ->where('is_active', true)
                ->exists();
        }

        return view('x-01::thread', [
            'messages' => $messages,
            'conversations' => $conversations,
        ]);
    }
}
