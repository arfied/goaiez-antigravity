<?php

declare(strict_types=1);

namespace Tests\Modules\X196;

use App\Modules\X196\Actions\ExtensionInjectAction;
use App\Modules\X196\Actions\ExtensionScanAction;
use App\Modules\X196\Events\ExtensionAborted;
use App\Modules\X196\Events\ExtensionTriggered;
use App\Modules\X196\Events\ProspectInjected;
use App\Modules\X196\Models\ExtensionSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class X196Test extends TestCase
{
    private ExtensionScanAction $scanAction;

    private ExtensionInjectAction $injectAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanAction = new ExtensionScanAction;
        $this->injectAction = new ExtensionInjectAction;
    }

    
}
