import glob

files = [
    'app/app/Modules/X-102/Ui/OfflineFormInbox.php',
    'app/app/Modules/X-140/Ui/ProposedPagesView.php',
    'app/app/Modules/X-142/Ui/ConnectYourAi.php',
    'app/app/Modules/X-142/Ui/McpTokenRegistry.php',
    'app/app/Modules/X-142/Ui/WebhooksView.php',
    'app/app/Modules/X-178/Ui/SiteEditorAssistant.php',
    'app/app/Modules/X-179/Ui/MatchScores.php',
    'app/app/Modules/X-179/Ui/ProspecttenantfacingTop3Preview.php',
    'app/app/Modules/X-192/Ui/MembershipsList.php'
]

for f in files:
    with open(f, 'r') as file:
        content = file.read()
    
    content = content.replace(
        'public function mount(): void {}',
        'public function mount(): void\n    {\n        abort_unless(auth()->check() && auth()->user()->hasRole(\\App\\Enums\\UserRole::Owner, \\App\\Enums\\UserRole::Manager, \\App\\Enums\\UserRole::SuperAdmin), 403);\n    }'
    )
    
    with open(f, 'w') as file:
        file.write(content)
