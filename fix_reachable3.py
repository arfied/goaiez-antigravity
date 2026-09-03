import re
import subprocess
import json

output = subprocess.check_output('cd app && php artisan doctor --stage=contract || true', shell=True).decode('utf-8')
lines = output.split('\n')
fixes = {}

for line in lines:
    m = re.search(r'· (X-\d+|C-[A-Za-z]+) @provides ([\w.]+): does not declare whether the agent may reach it', line)
    if m:
        mod = m.group(1)
        act = m.group(2)
        if mod not in fixes:
            fixes[mod] = []
        fixes[mod].append(act)

for mod in fixes.keys():
    subprocess.call(f'cd app && php artisan module:scaffold --module={mod}', shell=True)

subprocess.call('cd app && php artisan capabilities:scaffold', shell=True)
