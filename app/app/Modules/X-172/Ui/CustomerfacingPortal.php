<?php

declare(strict_types=1);

namespace App\Modules\X172\Ui;

use App\Modules\X172\Actions\PortalActionHandler;
use App\Modules\X172\Models\PortalLink;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CustomerfacingPortal extends Component
{
    public string $token = '';

    #[Locked]
    public int $businessId = 0;

    public string $activeTab = 'estimate'; // estimate, sign, pay, schedule, feedback

    public string $customerName = 'Sarah Jenkins';

    public string $serviceAddress = '742 Evergreen Terrace, Austin TX';

    public string $jobTitle = 'Main Water Line Replacement & Valve Pressure Testing';

    public string $estimateNumber = 'EST-2026-1042';

    public int $estimateTotalCents = 38500; // $385.00

    public array $lineItems = [
        ['name' => 'Emergency Dispatch Diagnostic & Pressure Test', 'qty' => 1, 'price' => 8500],
        ['name' => 'Heavy-Duty Ball Valve & Copper Coupling (3/4")', 'qty' => 2, 'price' => 12000],
        ['name' => 'Main Line Excavation & Pipe Fusion Labor', 'qty' => 1, 'price' => 18000],
    ];

    public string $signatureTyped = '';

    public bool $isSigned = false;

    public ?string $signedAt = null;

    public bool $isPaid = false;

    public ?string $paidAt = null;

    public string $paymentMethod = 'card';

    public string $cardLast4 = '4242';

    public string $selectedSlot = 'Today, 2:00 PM - 4:00 PM';

    public bool $isSlotConfirmed = true;

    public int $feedbackRating = 5;

    public string $feedbackComment = '';

    public bool $isFeedbackSubmitted = false;

    public ?string $statusMessage = null;

    public function mount(?string $token = null): void
    {
        if (! empty($token)) {
            $link = PortalLink::where('token', $token)->where('is_active', true)->first();
            if (! $link) {
                abort(404, 'Invalid or expired customer portal token');
            }
            $this->token = $token;
            $this->businessId = (int) $link->business_id;
        } else {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Portal link token or authenticated tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }

        Tenancy::set($this->businessId);
    }

    public function approveAndSign(): void
    {
        if (empty($this->signatureTyped)) {
            $this->statusMessage = 'Please type your full legal name to execute electronic signature.';

            return;
        }

        Tenancy::set($this->businessId);
        $handler = app(PortalActionHandler::class);
        $handler->handle($this->token, 'signature_signed', [
            'signer_name' => $this->signatureTyped,
            'signed_at' => now()->toIso8601String(),
            'terms_accepted' => true,
        ]);

        $this->isSigned = true;
        $this->signedAt = now()->format('M d, Y · h:i A');
        $this->activeTab = 'pay';
        $this->statusMessage = '✅ Estimate signed successfully! Proceeding to payment.';
    }

    public function submitPayment(): void
    {
        Tenancy::set($this->businessId);
        $handler = app(PortalActionHandler::class);
        $handler->handle($this->token, 'invoice_paid', [
            'amount_cents' => $this->estimateTotalCents,
            'payment_method' => $this->paymentMethod,
            'card_last4' => $this->cardLast4,
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->isPaid = true;
        $this->paidAt = now()->format('M d, Y · h:i A');
        $this->activeTab = 'feedback';
        $this->statusMessage = '🎉 Payment of $'.number_format($this->estimateTotalCents / 100, 2).' confirmed via Stripe!';
    }

    public function submitRating(): void
    {
        Tenancy::set($this->businessId);
        $handler = app(PortalActionHandler::class);
        $handler->handle($this->token, 'csat_feedback_submitted', [
            'rating' => $this->feedbackRating,
            'comment' => $this->feedbackComment,
        ]);

        $this->isFeedbackSubmitted = true;
        $this->statusMessage = '🌟 Thank you for your review! Your feedback was logged to the reputation engine.';
    }

    public function render()
    {
        return view('x-172::customerfacing-portal');
    }
}
