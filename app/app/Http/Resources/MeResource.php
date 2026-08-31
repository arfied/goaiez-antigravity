<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WizardProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function __construct(
        public readonly User $user,
        public readonly ?Business $business,
        public readonly ?Subscription $subscription,
        public readonly ?WizardProgress $wizard,
        public readonly array $balances = [],
        public readonly ?string $creditCurrency = null,
    ) {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'business' => $this->business ? [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'currency' => $this->business->currency,
            ] : null,
            'subscription' => $this->subscription ? [
                'id' => $this->subscription->id,
                'status' => $this->subscription->status,
            ] : null,
            'wizard' => $this->wizard ? [
                'step' => $this->wizard->step,
                'completed' => $this->wizard->completed,
            ] : null,
            'balances' => $this->balances,
            'credit_currency' => $this->creditCurrency,
        ];
    }
}
