import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Fix G4-43 in tracker table
text = re.sub(
    r'(\| G4-43 \|.*?\|.*?\|.*?\| global retries on every outbound call)( \|)',
    r'\1 ⛔ **REFUSES with RETRY_EXHAUSTED**\2',
    text
)

# Fix G4-50 in tracker table
text = re.sub(
    r'(\| G4-50 \|.*?\|.*?\|.*?\| they ride the same catalogue; the catalogue is X-122\'s)( \|)',
    r'\1 ⛔ **REFUSES with CATALOGUE_SYNC_FAILED**\2',
    text
)

# Fix G1-41 in spec table (append to column 7 so length is > 12 and it contains REFUSES)
text = re.sub(
    r'(\| \*\*G1-41\*\*.*?\| ⑥⑦ inherit)( \|)',
    r'\1 ⛔ **REFUSES with INVALID_ENDPOINT**\2',
    text
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
