<?php

declare(strict_types=1);

namespace Tests\Modules\X154;

use App\Modules\X154\Actions\LexiconApplyAction;
use App\Modules\X154\Actions\LexiconReadbackAction;
use App\Modules\X154\Events\LexiconUpdated;
use App\Modules\X154\Models\TenantLexicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X154Test extends TestCase
{
    private LexiconApplyAction $applyAction;

    private LexiconReadbackAction $readbackAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applyAction = new LexiconApplyAction;
        $this->readbackAction = new LexiconReadbackAction;
    }

    
}
