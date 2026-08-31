<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformMailSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformMailSend>
 */
final class PlatformMailSendFactory extends Factory
{
    protected $model = PlatformMailSend::class;

    public function definition(): array
    {
        return [
            'mailer' => 'array',
            // One of ours, never a recipient's — the table's own rule.
            'sending_account' => 'platform@mail.goaiez.test',
            'sent_at' => now(),
        ];
    }
}
