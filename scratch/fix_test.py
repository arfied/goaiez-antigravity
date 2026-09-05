with open('app/tests/Feature/NavigationTest.php', 'r') as f:
    content = f.read()

content = content.replace("config_path('features.php')", "__DIR__ . '/../../config/features.php'")
content = content.replace("config_path('surfaces.generated.php')", "__DIR__ . '/../../config/surfaces.generated.php'")

with open('app/tests/Feature/NavigationTest.php', 'w') as f:
    f.write(content)
