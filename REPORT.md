## Merge result
HEAD is not a merge — nothing to compare

## Shells remaining
shells remaining: 0

## Grep counts


## Gate line
tests 2646 · passed 2635 · FAILED 9 · errors 2

## Doctor first line
goaiez doctor · build 20260829-0647

## Mutation RED line
Failed asserting that '<!DOCTYPE html>\n
<html lang="en" class="antialiased dark">\n
<head>\n
 <meta ... 
</body>\n
</html>\n
' [UTF-8](length: 95316) contains "Unassigned Leads Queue" [ASCII](length: 22).

UNRESOLVED:
contract    X-186        — send.requested correctly emitted by multiple modules per R231, but sealed ContractStage.php lacks a uniqueness exemption and unconditionally fails.
    contract    X-190        — approval.requested correctly emitted by multiple modules per the master plan rule (same as send.requested shape), but sealed ContractStage.php lacks an exemption and unconditionally fails.
    contract    X-205        — approval.requested correctly emitted by multiple proposing modules per the master plan rule (same as send.requested shape), but sealed ContractStage.php lacks an exemption and unconditionally fails.
    contract    X-217        — send.requested correctly emitted by multiple modules per the master plan rule (R231), but sealed ContractStage.php lacks a uniqueness exemption and unconditionally fails.
    contract    X-218        — send.requested correctly emitted by multiple modules per the master plan rule (R231), but sealed ContractStage.php lacks a uniqueness exemption and unconditionally fails.
    tests       X-193        — column quiet_hours_start is missing from notification_classes and not named in the brief
    tests       X-201        — column deadline_at is missing from disputes and not named in the brief
    tests       C-Reviews    — ReviewRequested event lacks messageClass so the module cannot express the send class (marketing) without a legacy change
    contract    C-Reviews    — send.requested correctly emitted by multiple modules per the master plan rule (R231), but sealed ContractStage.php lacks a uniqueness exemption and unconditionally fails.
    capability  X-186        — no action enrols a person into a campaign
    tests       X-172        — generated screen test cannot mint a portal token (route x-172.customerfacing-portal/{token}); the module's own portal test is the coverage; needs a generator fixture hook
    capability  C-Sms        — TrialEligibility: The journey tenant needs a confirmed Google listing for real credit grant
    tests       X-01         — four legacy message tables outside the twelve nouns (outreach_messages, triage_conversations, inbound_messages, support_messages) — the G2-76 lint is right, main's schema is not migrated; the sixty lane proposes the fold
