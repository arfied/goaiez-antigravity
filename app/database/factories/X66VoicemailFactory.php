<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\X66\Models\Voicemail;
use Illuminate\Database\Eloquent\Factories\Factory;

class X66VoicemailFactory extends Factory
{
    protected $model = Voicemail::class;

    public function definition(): array
    {
        return [
            'audio_url' => 'https://example.com/audio1.mp3',
            'transcription' => $this->faker->sentence(),
        ];
    }
}
