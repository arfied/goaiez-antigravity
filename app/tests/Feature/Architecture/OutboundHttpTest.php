<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

function outboundLintCodeOnly(string $source): string
{
    $code = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $code .= $token[1];

            continue;
        }
        $code .= $token;
    }

    return $code;
}

it('forbids outbound Http:: calls outside the permitted files', function () {
    $finder = new Finder;
    $finder->files()
        ->in(app_path())
        ->name('*.php')
        ->exclude('Doctor');

    $offenders = [];
    foreach ($finder as $file) {
        $source = $file->getContents();
        if (opensOutboundSocket(outboundLintCodeOnly($source))) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $permittedFiles = outboundHttpPermittedFiles();

            if (! in_array($relativePath, $permittedFiles, true)) {
                $lines = explode("\n", $source);
                $lineNumber = '?';
                $foundVerb = 'unknown';

                foreach ($lines as $index => $line) {
                    if (preg_match_all('/\b(?i:Http::)([a-zA-Z]+)/', $line, $matches)) {
                        foreach ($matches[1] as $match) {
                            $verb = strtolower($match);
                            if (! in_array($verb, ['fake', 'preventstrayrequests', 'assertnothingsent'], true)) {
                                $lineNumber = $index + 1;
                                $foundVerb = $match;
                                break 2;
                            }
                        }
                    }
                }

                $offenders[] = $relativePath.':'.$lineNumber.' ('.$foundVerb.')';
            }
        }
    }

    expect($offenders)->toBe([], 'These files open an outbound socket outside the permitted list — route them through App\\Contracts\\FetchGateway (HTML) or a vendor client in outboundHttpPermittedFiles() (JSON APIs); never widen the list to make this green: '.implode(', ', $offenders));
});

it('detects an outbound socket when one is present', function () {
    $sourceWithSocket = 'Http::withHeaders(...)->post(...)';
    expect(opensOutboundSocket($sourceWithSocket))->toBeTrue();

    $sourceFake = 'Http::fake([...])';
    expect(opensOutboundSocket($sourceFake))->toBeFalse();
});

it('resolves every permitted file path', function () {
    $permittedFiles = outboundHttpPermittedFiles();
    foreach ($permittedFiles as $permittedFile) {
        $path = app_path($permittedFile);
        expect(file_exists($path))->toBeTrue("Permitted file does not exist: {$permittedFile}");
    }
});

it('ignores Http:: calls inside docblocks or comments', function () {
    $srcWithDocblock = "<?php\n/**\n * Http::post()\n */";
    expect(opensOutboundSocket(outboundLintCodeOnly($srcWithDocblock)))->toBeFalse();

    $srcWithRealCall = "<?php\n/**\n * Http::post()\n */\nHttp::post();";
    expect(opensOutboundSocket(outboundLintCodeOnly($srcWithRealCall)))->toBeTrue();
});
