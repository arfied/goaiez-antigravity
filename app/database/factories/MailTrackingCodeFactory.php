<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use App\Models\MailTrackingCode;
use App\Services\Mail\MailTrackingCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailTrackingCode>
 */
final class MailTrackingCodeFactory extends Factory
{
    protected $model = MailTrackingCode::class;

    public function definition(): array
    {
        return [
            // ⚠️ THE REAL ALPHABET, NOT `Str::random()`. A fixture carrying `I`,
            // `O` or a lowercase letter would be a code the resolver refuses,
            // and a test built on one would prove the refusal rather than the
            // path — passing for the wrong reason.
            'code' => collect(range(1, MailTrackingCodes::LENGTH))
                ->map(fn (): string => MailTrackingCodes::ALPHABET[random_int(
                    0,
                    mb_strlen(MailTrackingCodes::ALPHABET) - 1,
                )])
                ->implode(''),
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'outreach_message_id' => null,
            'created_at' => now(),
            'replied_at' => null,
        ];
    }
}
