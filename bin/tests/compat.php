<?php

declare(strict_types=1);

/**
 * The registration guard in functions.php, exercised against every Pollora
 * version this theme can land on.
 *
 * Why this suite exists: `make:theme` downloads this repository's latest tag
 * whatever framework the site is running, so one file — functions.php — has to
 * work on all of them. It did not: calling `pollora_register()` unconditionally
 * took every site still on framework 13.4 down with a fatal, before anything
 * could report why. That was found by hand, on one site, once. Nothing replayed
 * it. This does, on every push.
 *
 * Each case runs in its own interpreter with a stubbed environment, because
 * what the guard branches on — `function_exists('pollora_register')`, a bound
 * container — is global state that cannot be reset inside one process.
 *
 * The stubs stand in for the framework; they do not prove the real enum case
 * or the real contract still carry these names. That is what the install job
 * in .github/workflows/tests.yml measures, against a real site.
 */

/**
 * Compose one registration scenario and run it.
 *
 * @param  bool  $helper  whether `pollora_register()` exists (framework >= 13.32)
 * @param  string|null  $registrar  'ok', 'throws', or null for no binding at all
 * @param  bool  $laravel  whether `app()` exists at all (false = plain WordPress)
 * @return array{code: int, out: string}
 */
function registrationCase(bool $helper, ?string $registrar, bool $laravel = true): array
{
    $functions = var_export(themePath('functions.php'), true);

    $code = <<<'PRELUDE'
namespace Pollora\Modules\Domain\Enums {
    // Stand-in for the framework enum functions.php names in its `use`.
    enum ModuleType: string
    {
        case Theme = 'theme';
        case Plugin = 'plugin';
    }
}

namespace {
    $GLOBALS['trace'] = [];

    final class FakeRegistrar
    {
        public function __construct(private readonly bool $throws) {}

        public function register(): void
        {
            $GLOBALS['trace'][] = 'registrar::register';

            if ($this->throws) {
                throw new \RuntimeException('registrar exploded');
            }
        }
    }

    final class FakeLog
    {
        public function error(string $message): void
        {
            $GLOBALS['trace'][] = 'log::error';
        }
    }

    final class FakeContainer
    {
        /** @param array<string, object> $bindings */
        public function __construct(private readonly array $bindings) {}

        public function bound(string $abstract): bool
        {
            return isset($this->bindings[$abstract]);
        }

        public function make(string $abstract): object
        {
            return $this->bindings[$abstract];
        }
    }
}
PRELUDE;

    $registrarContract = 'Pollora\\Theme\\Domain\\Contracts\\ThemeRegistrarInterface';

    $bindings = "['log' => new FakeLog]";

    if ($registrar !== null) {
        $throws = $registrar === 'throws' ? 'true' : 'false';
        $bindings = "['log' => new FakeLog, '{$registrarContract}' => new FakeRegistrar({$throws})]";
    }

    $environment = "namespace {\n";

    if ($laravel) {
        $environment .= <<<PHP
    \$GLOBALS['container'] = new FakeContainer({$bindings});

    function app(?string \$abstract = null): object
    {
        return \$abstract === null ? \$GLOBALS['container'] : \$GLOBALS['container']->make(\$abstract);
    }

PHP;
    }

    if ($helper) {
        $environment .= <<<'PHP'
    function pollora_register(\Pollora\Modules\Domain\Enums\ModuleType $type): void
    {
        $GLOBALS['trace'][] = 'pollora_register::'.$type->value;
    }

PHP;
    }

    $environment .= <<<PHP
    require {$functions};

    echo implode(',', \$GLOBALS['trace']);
}
PHP;

    return phpRun($code."\n".$environment);
}

function checkRegistrationGuard(): void
{
    section('Registration guard — functions.php on every framework it can meet');

    test('Framework >= 13.32: registers through pollora_register()', function () {
        $result = registrationCase(helper: true, registrar: 'ok');

        if ($result['code'] !== 0) {
            return "fatal on the current framework: {$result['out']}";
        }

        return $result['out'] === 'pollora_register::theme'
            ? true
            : "expected pollora_register::theme, got '{$result['out']}'";
    });

    test('Framework >= 13.32: does not also call the registrar', function () {
        $result = registrationCase(helper: true, registrar: 'ok');

        return str_contains($result['out'], 'registrar::register')
            ? 'the theme registered twice — pollora_register() and the registrar both ran'
            : true;
    });

    test('Framework 13.4: falls back to the registrar contract', function () {
        $result = registrationCase(helper: false, registrar: 'ok');

        if ($result['code'] !== 0) {
            return "fatal on framework 13.4 — this is the regression that took stable sites down: {$result['out']}";
        }

        return $result['out'] === 'registrar::register'
            ? true
            : "expected registrar::register, got '{$result['out']}'";
    });

    test('Framework 13.4: a throwing registrar is logged, not fatal', function () {
        $result = registrationCase(helper: false, registrar: 'throws');

        if ($result['code'] !== 0) {
            return "the exception escaped and took the site down: {$result['out']}";
        }

        return $result['out'] === 'registrar::register,log::error'
            ? true
            : "expected the failure to be logged, got '{$result['out']}'";
    });

    test('Laravel present but nothing bound: leaves the theme unregistered', function () {
        $result = registrationCase(helper: false, registrar: null);

        if ($result['code'] !== 0) {
            return "fatal when the contract is unbound: {$result['out']}";
        }

        return $result['out'] === ''
            ? true
            : "expected no registration attempt, got '{$result['out']}'";
    });

    test('Plain WordPress, no Laravel at all: loads without a fatal', function () {
        $result = registrationCase(helper: false, registrar: null, laravel: false);

        if ($result['code'] !== 0) {
            return "fatal outside Pollora — the theme cannot even be listed: {$result['out']}";
        }

        return $result['out'] === ''
            ? true
            : "expected no registration attempt, got '{$result['out']}'";
    });
}

/**
 * The placeholders the scaffolder substitutes, with plausible values.
 *
 * Mirrors MakeThemeCommand::getReplacements(). A template file is not valid
 * PHP on its own — `namespace %theme_namespace%\Providers;` does not parse —
 * so linting the repository as it stands measures nothing. What has to parse
 * is what the user receives, which is this.
 *
 * @return array<string, string>
 */
function scaffolderReplacements(): array
{
    return [
        '%theme_name%' => 'my-theme',
        '%theme_camel%' => 'myTheme',
        '%theme_namespace%' => 'Theme\\MyTheme',
        '%theme_author%' => 'Someone',
        '%theme_author_uri%' => 'https://example.com',
        '%theme_uri%' => 'https://example.com/my-theme',
        '%theme_description%' => 'A theme',
        '%theme_version%' => '1.0.0',
    ];
}

/**
 * Every PHP file the theme ships has to parse once it is scaffolded.
 *
 * A parse error in a theme file is not a caught exception: WordPress serves a
 * white page and the admin cannot reach the screen that would disable it.
 */
function checkSyntax(): void
{
    section('Syntax — every PHP file parses once scaffolded');

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(themePath(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        // bin/ is stripped by the scaffolder, so it is not part of what ships —
        // and this very suite lives there, naming placeholders in its comments.
        if (str_contains($path, '/node_modules/') || str_contains($path, '/.git/') || str_contains($path, '/bin/')) {
            continue;
        }

        // Blade templates end in .php but are not PHP: `php -l` would reject
        // every directive in them. They are covered by the install job, which
        // actually renders them.
        if ($file->getExtension() === 'php' && ! str_ends_with($path, '.blade.php')) {
            $files[] = $path;
        }
    }

    sort($files);

    $replacements = scaffolderReplacements();

    test(count($files).' PHP files parse', function () use ($files, $replacements) {
        if ($files === []) {
            return 'no PHP file was found — the walk is looking in the wrong place';
        }

        $broken = [];

        foreach ($files as $file) {
            $scaffolded = str_replace(
                array_keys($replacements),
                array_values($replacements),
                (string) file_get_contents($file)
            );

            $temp = tempnam(sys_get_temp_dir(), 'theme-lint-').'.php';
            file_put_contents($temp, $scaffolded);
            $check = shell_exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1');
            unlink($temp);

            if (! is_string($check) || ! str_contains($check, 'No syntax errors')) {
                $broken[] = basename($file).': '.trim(str_replace($temp, basename($file), (string) $check));
            }
        }

        return $broken === [] ? true : implode(' | ', $broken);
    });

    // A token the scaffolder does not know is substituted by nothing: it ships
    // to the user as literal "%theme_slug%" in the file that needed it.
    test('No file uses a placeholder the scaffolder does not substitute', function () use ($files, $replacements) {
        $known = array_keys($replacements);
        $unknown = [];

        foreach ($files as $file) {
            if (preg_match_all('/%[a-z_]+%/i', (string) file_get_contents($file), $matches) === 0) {
                continue;
            }

            foreach (array_unique($matches[0]) as $token) {
                if (! in_array($token, $known, true)) {
                    $unknown[] = basename($file).": {$token}";
                }
            }
        }

        return $unknown === []
            ? true
            : implode(', ', $unknown).' — the scaffolder leaves these in place verbatim';
    });
}
