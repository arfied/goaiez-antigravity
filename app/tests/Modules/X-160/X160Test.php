<?php

declare(strict_types=1);

namespace Tests\Modules\X160;

use App\Modules\X121\Models\Fact;
use App\Modules\X160\Actions\DocumentConfirmAction;
use App\Modules\X160\Actions\DocumentIngestAction;
use App\Modules\X160\Actions\DocumentReviewAction;
use App\Modules\X160\Domain\DocumentExtractionEngine;
use App\Modules\X160\Events\DocumentIngested;
use App\Modules\X160\Events\DocumentReviewed;
use App\Modules\X160\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X160Test extends TestCase
{
    private DocumentExtractionEngine $engine;

    private DocumentIngestAction $ingestAction;

    private DocumentReviewAction $reviewAction;

    private DocumentConfirmAction $confirmAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DocumentExtractionEngine;
        $this->ingestAction = new DocumentIngestAction($this->engine);
        $this->reviewAction = new DocumentReviewAction($this->engine);
        $this->confirmAction = new DocumentConfirmAction;
    }

    
}
