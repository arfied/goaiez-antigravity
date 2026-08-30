<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use Livewire\Component;

class ComposerSegmentWarning extends Component
{
    public string $body = '';

    public function render()
    {
        return view('c-sms::composer-segment-warning');
    }
}
