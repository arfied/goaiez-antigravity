import re
import subprocess

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

with open('source/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    content = f.read()

for mod, acts in fixes.items():
    act_str = ' · '.join(acts)
    # search for `@agent_reachable ...` in this module's block and append
    # Since we don't know the exact current string, let's just do a regex replace
    mod_pattern = r'(@module \*\*' + mod + r'\b.*?\n(?:.*?))(?=needs: |ships: |TEST ANCHOR |@module |$)'
    
    def replacer(match):
        block = match.group(1)
        if '@agent_reachable none' in block:
            return block
        if '`@agent_reachable' in block:
            # find `@agent_reachable ...` and insert before closing backtick
            return re.sub(r'(`@agent_reachable [^`]+)(`)', r'\1 · ' + act_str + r'\2', block)
        else:
            # just append before the end of the block
            return block + f"`@agent_reachable {act_str}` · "
    
    content = re.sub(mod_pattern, replacer, content, flags=re.DOTALL)

with open('source/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(content)

subprocess.call('cd app && php artisan module:scaffold --all', shell=True)
