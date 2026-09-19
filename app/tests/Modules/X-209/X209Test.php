<?php

declare(strict_types=1);

namespace Tests\Modules\X209;

use App\Modules\X209\Actions\FixerApproveAction;
use App\Modules\X209\Actions\FixerCommandAction;
use App\Modules\X209\Actions\FixerDelegateAction;
use App\Modules\X209\Events\FixerActionTaken;
use App\Modules\X209\Events\FixerCommandReceived;
use App\Modules\X209\Events\FixerEscalated;
use App\Modules\X209\Events\FixerPromoted;
use App\Modules\X209\Models\FixerCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X209Test extends TestCase
{
    private FixerCommandAction $commandAction;

    private FixerApproveAction $approveAction;

    private FixerDelegateAction $delegateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandAction = new FixerCommandAction;
        $this->approveAction = new FixerApproveAction;
        $this->delegateAction = new FixerDelegateAction;
    }

    
}
