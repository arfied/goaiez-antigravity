import sys

with open('app/app/Support/Account/OwnerNav.php', 'r') as f:
    lines = f.readlines()

new_lines = []
in_conflict = False
for line in lines:
    if line.startswith('<<<<<<< HEAD'):
        in_conflict = True
    elif line.startswith('======='):
        pass
    elif line.startswith('>>>>>>> origin/track/money'):
        in_conflict = False
    else:
        new_lines.append(line)

with open('app/app/Support/Account/OwnerNav.php', 'w') as f:
    f.writelines(new_lines)
