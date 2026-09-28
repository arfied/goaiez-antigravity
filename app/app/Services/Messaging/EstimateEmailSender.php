<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\OutreachStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\OutreachMessage;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X164\Actions\EstimateReadAction;
use App\Modules\X164\Actions\EstimateSendAction;
use App\Modules\X164\Models\Estimate;
use App\Notifications\EstimateEmail;
use App\Services\Billing\EmailCredits;
use App\Services\Consent\ConsentService;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\Outbound\SendKey;
use App\Support\Identifier;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EstimateEmailSender
{
    public function __construct(
        private readonly SendingGuard $guard,
        private readonly PlatformMailer $mailer,
        private readonly ConsentService $consent,
        private readonly EmailCredits $emailCredits,
    ) {}

    public function send(int $estimateId): array
    {
        $businessId = Tenancy::idOrFail();

        $estimateData = app(EstimateReadAction::class)->forPortal($businessId, $estimateId);
        if ($estimateData === null) {
            return ['sent' => false, 'message' => 'That estimate is not here any more.'];
        }

        $estimate = Estimate::where('business_id', $businessId)->find($estimateId);
        if ($estimate === null || $estimate->customer_id === null) {
            return ['sent' => false, 'message' => 'Not sent — this estimate has no customer email.'];
        }

        // The person's email is read through EntityReadAction
        $person = app(EntityReadAction::class)->handle('people', $estimate->customer_id, $businessId);

        if ($person === null || empty($person['email'])) {
            return ['sent' => false, 'message' => 'Not sent — this estimate has no customer email.'];
        }

        $normalised = Identifier::normalise($person['email'], OutreachChannel::Email);
        if ($normalised === null) {
            return ['sent' => false, 'message' => 'Not sent — this estimate has no customer email.'];
        }

        $customer = Customer::query()->where('email', $normalised)->first();
        if ($customer === null) {
            return ['sent' => false, 'message' => 'Not sent — nobody with that email is in your customer list. Add them as a customer first.'];
        }

        $containment = $this->guard->refusalFor(OutreachChannel::Email);
        if ($containment !== null) {
            return ['sent' => false, 'message' => 'Not sent — email to this customer is not allowed right now: '.$containment->ownerSentence(OutreachChannel::Email).'.'];
        }

        $mailRefusal = $this->mailer->customerMailRefusal();
        if ($mailRefusal !== null) {
            return ['sent' => false, 'message' => 'Not sent — email to this customer is not allowed right now: '.$mailRefusal->getMessage()];
        }

        $decision = $this->consent->decide(
            $customer,
            OutreachChannel::Email,
            OutreachPurpose::Transactional,
        );

        if (! $decision->isGranted()) {
            return ['sent' => false, 'message' => 'Not sent — email to this customer is not allowed right now: '.$decision->reason->ownerSentence(OutreachChannel::Email).'.'];
        }

        $permit = $decision->permit;
        $key = SendKey::for($permit, 'estimate:'.$estimate->id);

        try {
            $message = DB::transaction(function () use ($customer, $permit, $key, $estimateData, $estimate, $businessId): OutreachMessage {
                $businessName = Business::findOrFail($businessId)->name;

                /** @var OutreachMessage $message */
                $message = $this->emailCredits->debitForSend(
                    recordTheSend: fn (): OutreachMessage => OutreachMessage::create([
                        'business_id' => $businessId,
                        'location_id' => null,
                        'customer_id' => $customer->id,
                        'channel' => OutreachChannel::Email,
                        'purpose' => OutreachPurpose::Transactional,
                        'lane' => $permit->lane,
                        'send_key' => $key->value,
                        'media_count' => 0,
                        'status' => OutreachStatus::Queued,
                        'sent_at' => now(),
                        'created_at' => now(),
                    ]),
                    refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
                );

                $expiresOn = $estimate->expires_at ? $estimate->expires_at->format('j M Y') : null;
                $mappedLines = array_map(fn ($line) => [
                    'service_name' => $line['label'],
                    'quantity' => $line['quantity'],
                    'subtotal_cents' => $line['subtotal_cents'],
                ], $estimateData['lines']);

                $emailNotification = new EstimateEmail(
                    $businessName,
                    $estimateData['number'],
                    $mappedLines,
                    $estimateData['total_cents'],
                    $expiresOn
                );

                $this->mailer->sendToCustomer(
                    $permit,
                    $emailNotification,
                    $businessName,
                    $message,
                );

                return $message;
            });
        } catch (QueryException $e) {
            if (SqlState::of($e) !== '23505') {
                throw $e;
            }

            return ['sent' => false, 'message' => 'Already emailed.'];
        }

        app(EstimateSendAction::class)->handle($businessId, $estimate->id);

        return ['sent' => true, 'message' => 'Emailed estimate '.$estimateData['number'].' to '.$person['email'].'.'];
    }
}
