import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

def add_refusal(spec_id, text_to_add):
    global text
    # The table row format is `| **ID** | Name | ... | Data | Failure | Test | 67 |`
    # Let's find the row starting with `| **G4-42** |` or `| **G11-14** · **G17-28** |`
    pattern = r'(\| \*\*' + spec_id + r'\*\*.*?\|.*?\|.*?\|.*?\|)(.*?)(\s*\|\s*⑥⑦.*)'
    
    def repl(m):
        col5 = m.group(2)
        if 'REFUSES' not in col5:
            col5 += text_to_add
        return m.group(1) + col5 + m.group(3)
        
    text = re.sub(pattern, repl, text)

add_refusal('G4-42', ' · ⛔ **REFUSES with ROLLBACK_FAILED if the state is unrecoverable**')
add_refusal('G4-51', ' · ⛔ **REFUSES with MIGRATION_BLOCKED if active readers are on the old shape**')
add_refusal('G11-14', ' · ⛔ **REFUSES with HISTORY_UNAVAILABLE if retention period exceeded**')

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
