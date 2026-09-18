<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

use App\Models\Conversation;
use App\Models\Message;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Actions\ArRecordReasonAction;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Models\ArDunningAction;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Invoice thread'])]
class InvoiceThreadBeside extends Component
{
    use LabelsDunningAction;

    private const THREAD_WINDOW = 50;

    public ?int $invoiceId = null;

    public array $reason = [];

    public ?string $error = null;

    public ?string $success = null;

    public function mount(?int $invoiceId = null): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function pick(int $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
        $this->error = null;
        $this->success = null;
    }

    public function recordReason(int $invoiceId, ArRecordReasonAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $businessId = Tenancy::idOrFail();

        $code = (string) ($this->reason[$invoiceId] ?? '');
        if (! array_key_exists($code, ArEngine::REASONS)) {
            $this->error = 'Pick one of the reasons.';

            return;
        }

        try {
            $recorded = $action->handle($businessId, $invoiceId, $code);
            $this->invoiceId = $invoiceId;
            $this->success = 'Recorded: '.$recorded->reason.'.';
            unset($this->reason[$invoiceId]);
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not record that: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $bizId = Tenancy::idOrFail();

        $invoices = app(InvoiceReader::class)->openForBusiness($bizId);

        $invoice = $invoices->firstWhere('id', $this->invoiceId) ?? $invoices->first();

        $lines = collect();
        $messages = collect();
        $actions = collect();
        $customer = null;
        $escalation = null;
        $threadTruncated = false;

        if ($invoice) {
            $invoice->balance_cents = $invoice->total_cents - $invoice->paid_cents;
            $invoice->days_overdue = $invoice->due_date->isPast() ? (int) $invoice->due_date->diffInDays(today()) : 0;
            $lines = app(InvoiceReader::class)->linesFor($bizId, $invoice->id);
            $customer = $invoice->customer_id ? app(EntityReadAction::class)->handle('people', (int) $invoice->customer_id, $bizId) : null;

            if ($customer) {
                $conversationIds = Conversation::where('business_id', $bizId)->where('person_id', $invoice->customer_id)->pluck('id');
                $messages = Message::where('business_id', $bizId)
                    ->whereIn('conversation_id', $conversationIds)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(self::THREAD_WINDOW + 1)
                    ->get();

                // One row past the window is the overflow probe: no second query, no count().
                $threadTruncated = $messages->count() > self::THREAD_WINDOW;
                $messages = $messages->take(self::THREAD_WINDOW)->reverse()->values();
            }

            $actions = ArDunningAction::where('business_id', $bizId)->where('invoice_id', $invoice->id)->latest('id')->get();
            $escalation = $actions->firstWhere('action', 'escalate_to_human');
        }

        return view('x-211::invoice-thread-beside', [
            'invoices' => $invoices,
            'invoice' => $invoice,
            'lines' => $lines,
            'customer' => $customer,
            'messages' => $messages,
            'threadTruncated' => $threadTruncated,
            'actions' => $actions,
            'escalation' => $escalation,
            'reasons' => ArEngine::REASONS,
            'actionLabels' => $this->dunningActionLabels(),
        ]);
    }
}
