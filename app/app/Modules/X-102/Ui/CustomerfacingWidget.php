<?php

declare(strict_types=1);

namespace App\Modules\X102\Ui;

use App\Enums\UserRole;
use App\Modules\X102\Actions\ChatEscalateAction;
use App\Modules\X102\Models\ChatSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Chat widget'])]
class CustomerfacingWidget extends Component
{
    public ?string $error = null;

    public ?string $success = null;

    public function escalate(int $sessionId): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);

        $this->error = null;
        $this->success = null;

        $result = app(ChatEscalateAction::class)->handle($this->businessId, $sessionId, 'owner_asked_for_a_human');

        if ($result['status'] !== 'escalated') {
            $this->error = 'Nobody left a phone number or email in this conversation, so there is no way to reach them. It cannot be handed to a person yet.';

            return;
        }

        $this->success = 'Handed to a person. The conversation now reads escalated.';
    }

    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $sessions = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->orderByDesc('id')->limit(20)->get() : collect();
        $total = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->count() : 0;
        $active = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->where('status', 'active')->count() : 0;
        $escalated = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->where('status', 'escalated')->count() : 0;

        return view('x-102::customerfacing-widget', compact('sessions', 'total', 'active', 'escalated'));
    }
}
