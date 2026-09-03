import re
with open('app/tests/Modules/X-210/X210Test.php', 'r') as f:
    text = f.read()

text = text.replace("[N-027], [N-028], [N-030],", "[N-027], [N-028], [N-030], [N-032],")

with open('app/tests/Modules/X-210/X210Test.php', 'w') as f:
    f.write(text)
