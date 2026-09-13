<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X199\Actions\TermsSetAction;
use App\Modules\X199\Domain\InvalidTermsException;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\OverflowCharge;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Credit Balances & Terms'])]
class Credits extends Component
{
    public const LABELS = [
        'due_on_receipt' => 'due on receipt',
        'net_15' => 'net 15',
        'net_30' => 'net 30',
        'net_60' => 'net 60',
    ];

    /** @var array<int,string> keyed by credit_terms.id */
    public array $termsType = [];

    /** @var array<int,string|int|float> whole dollars, keyed by credit_terms.id */
    public array $limit = [];

    public ?string $error = null;

    public ?string $success = null;

    public function setTerms(int $termId, TermsSetAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $businessId = Tenancy::idOrFail();

        try {
            $term = CreditTerm::where('business_id', $businessId)->findOrFail($termId);
            $type = (string) ($this->termsType[$termId] ?? $term->terms_type);
            $limitCents = (int) round(((float) ($this->limit[$termId] ?? ($term->credit_limit_cents / 100))) * 100);

            $updated = $action->handle($businessId, (int) $term->customer_id, $type, $limitCents);

            $name = $this->customerName((int) $term->customer_id);
            $this->success = sprintf(
                'Terms on %s: %s, limit %s.',
                $name,
                self::LABELS[$updated->terms_type] ?? $updated->terms_type,
                number_format($updated->credit_limit_cents / 100, 2)
            );
            unset($this->termsType[$termId], $this->limit[$termId]);
        } catch (InvalidTermsException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That customer isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not set those terms: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $terms = CreditTerm::where('business_id', $businessId)->orderBy('id')->get();
        $people = [];
        $customerIds = array_unique($terms->pluck('customer_id')->filter()->all());
        $entityReader = app(EntityReadAction::class);
        foreach ($customerIds as $customerId) {
            $people[$customerId] = $entityReader->handle('people', (int) $customerId, $businessId);
        }

        $charges = OverflowCharge::where('business_id', $businessId)
            ->whereIn('customer_id', $terms->pluck('customer_id')->filter()->all())
            ->orderByDesc('id')
            ->get()
            ->groupBy('customer_id');

        foreach ($terms as $term) {
            $person = $people[$term->customer_id] ?? null;
            $term->customer_name = $person ? trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? '')) : 'Customer #'.$term->customer_id;
            $term->headroom_cents = $term->credit_limit_cents - $term->current_outstanding_cents;
            $term->label = self::LABELS[$term->terms_type] ?? $term->terms_type;

            $forCustomer = $charges[$term->customer_id] ?? collect();
            $reversedInvoices = $forCustomer->where('charge_type', 'overflow_reversed')->pluck('invoice_id')->all();
            $latestCharged = $forCustomer->where('charge_type', 'overflow_charged')->first();

            $term->overflow = match (true) {
                $latestCharged === null => 'no overflow yet',
                // 'refused' covers three facts, and only two of them mean the card said no.
                // A charge the gateway took but has not settled arrives with a real id on
                // reference_id, so the id is the discriminator the row already carries.
                $latestCharged->status === 'refused' && $latestCharged->reference_id !== null => 'the gateway took it and has not settled it yet',
                $latestCharged->status === 'refused' => 'the card did not absorb it and the invoice still stands',
                in_array($latestCharged->invoice_id, $reversedInvoices, true) => 'reversed against the invoice',
                default => 'covered by the card on file; service never stopped',
            };
        }

        return view('x-199::credits', ['terms' => $terms]);
    }

    private function customerName(int $customerId): string
    {
        $person = app(EntityReadAction::class)->handle('people', $customerId, Tenancy::idOrFail());

        return $person ? trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? '')) : 'Customer #'.$customerId;
    }
}
