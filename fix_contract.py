import re
import subprocess
import os

output = subprocess.check_output('cd app && php artisan doctor --stage=contract || true', shell=True).decode('utf-8')
lines = output.split('\n')

for line in lines:
    # 1. Unresolved dependencies
    m = re.search(r'· (.*?): consumes \'(.*?)\' — nothing emits it', line)
    if m:
        modules = m.group(1).split(', ')
        event = m.group(2)
        for mod in modules:
            mod = mod.strip()
            cmd = f'python3 bin/state.py unresolved {mod} contract "consumes {event} nothing emits it"'
            print(cmd)
            os.system(cmd)
            
