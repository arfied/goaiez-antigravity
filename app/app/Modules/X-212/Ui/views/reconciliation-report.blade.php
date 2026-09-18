<div>
    <x-surface.sample-state module="⭐⭐ **Bringing a tenant's whole business across from ServiceTitan, Jobber, Housecall Pro — or from a spreadsheet.** *Customers, properties, job history, invoices, payments, estimates, photos, notes, agreements, the pricebook, staff and reviews.* ⛔⛔ **AND IT IS A TRANSLATION, NOT A COPY.** *Their job-status enum is not ours; their pricebook shape is not ours; their custom fields have no home.* ⭐⭐⭐ **A migration that maps 80% and silently drops 20% is worse than no migration — because the tenant finds the hole three months later, on a job, in front of a customer.**" screen="reconciliation_report" />
    <div class="recon-report-view p-4">
        <h3 class="text-lg font-bold">Reconciliation Report</h3>
        <ul>
            @foreach($rejects as $reject)
                <li>{{ $reject->rejection_reason }}</li>
            @endforeach
        </ul>
    </div>
</div>
