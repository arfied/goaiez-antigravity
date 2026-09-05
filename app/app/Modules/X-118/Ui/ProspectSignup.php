<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use App\Models\User;
use App\Modules\X118\Actions\OnboardingStartAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class ProspectSignup extends Component
{
    public string $businessName = '';

    public string $contactPhone = '';

    public ?string $errorMessage = null;

    public bool $isSuccess = false;

    protected $rules = [
        'businessName' => 'required|min:3',
        'contactPhone' => 'required|min:7',
    ];

    public function startSignup()
    {
        $this->validate();
        $this->errorMessage = null;

        // Catch omitted so we can see the real error
        $user = User::create([
            'name' => $this->businessName.' Owner',
            'email' => 'prospect_'.uniqid().'@example.com',
            'password' => bcrypt(Str::random(16)),
        ]);

        Auth::login($user);

        $starter = app(OnboardingStartAction::class);
        $starter->handle($user, $this->businessName, $this->contactPhone);

        $this->isSuccess = true;
    }

    public function render()
    {
        return view('x-118::prospect-signup');
    }
}
