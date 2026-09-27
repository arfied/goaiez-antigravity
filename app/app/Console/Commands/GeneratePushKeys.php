<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:vapid-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print a new VAPID key pair for browser push, as two .env lines';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line("WEBPUSH_VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("WEBPUSH_VAPID_PRIVATE_KEY={$keys['privateKey']}");
        $this->line('Put both lines in the .env file or in Ops → Platform → Credentials, then run php artisan config:cache. Rotating them signs every browser out of alerts.');

        return 0;
    }
}
