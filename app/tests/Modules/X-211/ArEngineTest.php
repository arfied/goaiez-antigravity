<?php

use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Models\ArDunningAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;


test('dunning sequence escalates before suspend', function () {
    $business = \App\Models\Business::factory()->create();
    $customer = \App\Modules\X121\Models\Person::create(['business_id' => $business->id]);
    
    $engine = app(\App\Modules\X199\Domain\InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => 40000]],
        'net_30'
    );
    $invoice = $result['invoice'];
    
    $event = new ArOverdue($business->id, $invoice->id, 10);
    $listener = new \App\Modules\X211\Listeners\ProcessOverdueReceivable();
    $listener->handle($event);
    
    $action = ArDunningAction::where('invoice_id', $invoice->id)->latest('id')->first();
    
    expect($action)->not->toBeNull();
    expect($action->action)->toBe('escalate_to_human');
    expect($action->reason)->toContain('resolution attempt before any suspension');
});
