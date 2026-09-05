import re

with open('app/app/Console/Commands/SurfacesGenerateCommand.php', 'r') as f:
    content = f.read()

replacement = """                $featuresConfig = is_file(config_path('features.php')) ? require config_path('features.php') : ['entries' => [], 'deferred' => []];
                $navGroup = 'Unplaced';
                $isDeferred = in_array($modId, $featuresConfig['deferred'] ?? []);
                
                if (! $isDeferred) {
                    foreach ($featuresConfig['entries'] ?? [] as $entry) {
                        if (in_array($modId, $entry['modules'] ?? [])) {
                            $navGroup = $entry['label'];
                            break;
                        }
                    }
                    if ($navGroup === 'Unplaced') {
                        // The test expects us to count them and print their ID. We can print it directly here or track it.
                        // Wait, to print we can use $this->info
                        // Actually let's track it in a property or static array to print at the end.
                        self::$unplacedModules[$modId] = true;
                    }
                }"""

# We need to replace lines 171 to 191 (the group map)
content = re.sub(
    r"\$rawGroup = \$moduleGroups\[\$modId\].*?\$navGroup = \$groupMap\[\$rawGroup\] \?\? 'Settings';",
    replacement,
    content,
    flags=re.DOTALL
)

# And when adding to $allNavGroups:
add_to_nav = """                    $navRoute = $surf === 'operator' ? "$alias.admin" : $alias;
                    if (!$isDeferred) {
                        $allNavGroups[$surf][$navGroup][] = [
                            'label' => $humanName,
                            'route' => $navRoute,
                            'module' => $modId,
                        ];
                    }"""

content = re.sub(
    r"\$navRoute = \$surf === 'operator' \? \"\$alias.admin\" : \$alias;\s+\$allNavGroups\[\$surf\]\[\$navGroup\]\[\] = \[\s+'label' => \$humanName,\s+'route' => \$navRoute,\s+'module' => \$modId,\s+\];",
    add_to_nav,
    content
)

# Add static property for unplaced tracking
content = content.replace('class SurfacesGenerateCommand extends Command\n{', 'class SurfacesGenerateCommand extends Command\n{\n    public static array $unplacedModules = [];')

# Print at the end of handle()
end_handle = """        $this->generateSurfacesConfig($allNavGroups);
        $this->generateSharedComponent();

        $unplacedCount = count(self::$unplacedModules);
        $this->line("unplaced: {$unplacedCount}");
        foreach (array_keys(self::$unplacedModules) as $u) {
            $this->line(" - {$u}");
        }

        return 0;"""

content = content.replace("""        $this->generateSurfacesConfig($allNavGroups);
        $this->generateSharedComponent();

        return 0;""", end_handle)

with open('app/app/Console/Commands/SurfacesGenerateCommand.php', 'w') as f:
    f.write(content)
