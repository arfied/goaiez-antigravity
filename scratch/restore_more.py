files = [
    'app/app/Modules/X-179/Ui/MatchScores.php',
    'app/app/Modules/X-179/Ui/ProspecttenantfacingTop3Preview.php'
]

for f in files:
    with open(f, 'r') as file:
        content = file.read()
    
    # insert inside mount() {
    content = content.replace(
        'public function mount(int $prospectId)\n    {',
        'public function mount(int $prospectId)\n    {\n        abort_unless(auth()->check() && auth()->user()->hasRole(\\App\\Enums\\UserRole::Owner, \\App\\Enums\\UserRole::Manager, \\App\\Enums\\UserRole::SuperAdmin), 403);'
    )
    
    with open(f, 'w') as file:
        file.write(content)
