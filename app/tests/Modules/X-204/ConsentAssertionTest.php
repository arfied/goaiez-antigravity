<?php

declare(strict_types=1);

namespace Tests\Modules\X204;

use App\Modules\X204\Actions\ConsentDecideAction;
use App\Modules\X204\Actions\ConsentSuppressAction;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsentAssertionTest extends TestCase
{
    private ConsentService $consent;
    private ConsentDecideAction $decider;
    private ConsentSuppressAction $suppressor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consent = new ConsentService;
        $this->decider = new ConsentDecideAction($this->consent);
        $this->suppressor = new ConsentSuppressAction($this->consent);
    }
}
