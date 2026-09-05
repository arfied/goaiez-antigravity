<?php
$file = __DIR__.'/../app/app/Console/Commands/SurfacesGenerateCommand.php';
$content = file_get_contents($file);
$content = str_replace(
    "\$biz = \$this->provisionTenant();\\n        \$owner = User::where('business_id', \$biz->id)->first();\\n        \$owner->role = UserRole::Owner;\\n        \$owner->save();\\n        \$this->actingAs(\$owner);",
    "\$owner = User::factory()->create(['role' => UserRole::Owner]);\\n        \$biz = \$this->provisionTenant(['owner_user_id' => \$owner->id]);\\n        \$this->actingAs(\$owner);",
    $content
);
file_put_contents($file, $content);
