<?php
$file = __DIR__.'/../app/app/Console/Commands/SurfacesGenerateCommand.php';
$content = file_get_contents($file);

$old = <<<'PHP'
            $content .= "        \$biz = \$this->provisionTenant();\n";
            $content .= "        \$owner = User::where('business_id', \$biz->id)->first();\n";
            $content .= "        \$owner->role = UserRole::Owner;\n";
            $content .= "        \$owner->save();\n";
            $content .= "        \$this->actingAs(\$owner);\n";
PHP;

$new = <<<'PHP'
            $content .= "        \$owner = User::factory()->create(['role' => UserRole::Owner]);\n";
            $content .= "        \$biz = \$this->provisionTenant(['owner_user_id' => \$owner->id]);\n";
            $content .= "        \$this->actingAs(\$owner);\n";
PHP;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
