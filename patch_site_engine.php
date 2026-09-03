<?php
$content = file_get_contents('app/app/Modules/X-103/Domain/SiteEngine.php');

$search = <<<PHP
            \$version = PageVersion::create([
                'business_id' => \$businessId,
                'page_id' => \$page->id,
                'commit_id' => \$commitId,
                'content_blocks' => \$contentBlocks,
                'pixel_installed' => true, // G9-04 full-stack site law
            ]);
PHP;
$replace = <<<PHP
            \$required = ['chat_widget', 'form_capture', 'dni_script', 'seo_tags', 'schema_markup'];
            foreach (\$required as \$type) {
                \$found = false;
                foreach (\$contentBlocks as \$block) {
                    if (isset(\$block['type']) && \$block['type'] === \$type) {
                        \$found = true;
                        break;
                    }
                }
                if (!\$found) {
                    \$contentBlocks[] = ['type' => \$type];
                }
            }

            \$version = PageVersion::create([
                'business_id' => \$businessId,
                'page_id' => \$page->id,
                'commit_id' => \$commitId,
                'content_blocks' => \$contentBlocks,
                'pixel_installed' => true, // G9-04 full-stack site law
                'ssl_installed' => true,
            ]);
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/app/Modules/X-103/Domain/SiteEngine.php', $content);
