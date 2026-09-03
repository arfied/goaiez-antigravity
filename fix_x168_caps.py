with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

replacement = """**TEST ANCHOR** *`grep -rE 'location|gps' app/Modules/X-168/` shows reads only inside a job-state window; a location event with no active job writes nothing*

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-063** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-064** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-067** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-070** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-072** | JobTime Core | fails | `none` | fails | arrival, duration and completion on a JOB — not a timesheet · GPS clock-in is bound to job state · ⛔ **REFUSES with HR_REJECTED** |
| **N-073** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-074** | JobTime Core | fails | `none` | fails | arrival, duration and completion on a JOB — not a timesheet · GPS clock-in is bound to job state · ⛔ **REFUSES with HR_REJECTED** |
| **N-076** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-079** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-082** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
| **N-085** | JobTime Core | fails | `none` | fails | ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · ⛔ **REFUSES with HR_REJECTED** |
"""

text = text.replace("**TEST ANCHOR** *`grep -rE 'location|gps' app/Modules/X-168/` shows reads only inside a job-state window; a location event with no active job writes nothing*", replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
