<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Actions\StaffInviteAction;
use App\Modules\X113\Models\StaffUser;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Staff'])]
class Staff extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $name = '';

    public string $email = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function invite(StaffInviteAction $action): void
    {
        $this->success = null;
        $this->error = null;

        $name = trim($this->name);
        $email = trim($this->email);

        if ($email === '') {
            $this->error = 'Email is required.';

            return;
        }

        if ($name === '') {
            $this->error = 'Name is required.';

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'That is not a valid email address.';

            return;
        }

        $businessId = Tenancy::idOrFail();

        $exists = StaffUser::where('business_id', $businessId)->where('email', $email)->exists();
        if ($exists) {
            $this->error = 'This email is already on the crew.';

            return;
        }

        $user = $action->handle($businessId, $email, $name, null);

        $this->success = 'Invited '.$user->name.'. This feeds the staff list; nothing downstream is wired to it yet.';

        $this->name = '';
        $this->email = '';
    }

    public function render()
    {
        $staff = ($this->businessId > 0)
            ? StaffUser::where('business_id', $this->businessId)->orderBy('name')->get()
            : collect();

        return view('x-113::staff', [
            'staff' => $staff,
        ]);
    }
}
