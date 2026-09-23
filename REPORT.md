# REPORT

* LINES-READ: ~3500

## Refusal codes covered per job:
**SendAgentNudgeJob**:
- `nudge_not_found`
- `already_settled`
- `window_closed`
- `switched_off`
- `thread_gone`
- `agent_may_not_speak`
- `customer_replied`
- `contact_gone`
- `quiet_hours` (daytime-window hold)
- `no_consent_record` (ConsentService::decide refusal)

**SendRecoveryCheckInJob**:
- `conversation_missing`
- `already_offered` (RecoveryCheckInSender::attempt refusal)

**AdvanceFirstWeekPathJob**:
- `business_missing` (not reachable but theoretically covered as no DB hits occur for this path if we assert not started)
- `not_started`

## The ROW each happy path asserts:
- **SendAgentNudgeJob**: `send_key` in the output of the automation run.
- **SendRecoveryCheckInJob**: `sent => true` in the output of the automation run.
- **AdvanceFirstWeekPathJob**: The `day_0` step in the output of the automation run.

GATE: not run — the supervisor gates this wave.
