#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scaffold this theme onto a real, installed Pollora site and check it works.
 *
 * Run from the skeleton root, with WordPress already installed:
 *
 *   php /path/to/theme-default/bin/ci/check-install.php \
 *       --url=https://pollora-theme-ci.ddev.site [--slug=ci-theme] [--source=/path/to/theme-default]
 *
 * The contract tests next door are properties of the files. This is the part
 * they cannot see: that a generated theme registers with the framework the
 * site actually runs, that its views resolve through whichever rendering path
 * that framework uses, and that its blocks arrive named after the generated
 * theme rather than after this template.
 *
 * The case worth the whole script is a skeleton on framework 13.4: it renders
 * a post through `Route::wp('single', fn () => view('post'))` and never
 * consults the template hierarchy, so it is broken by changes that look
 * harmless from here. That combination was measured by hand, once, and
 * nothing replayed it.
 *
 * --source overlays the working copy onto the generated theme, so CI measures
 * the commit under test rather than the tag the scaffolder downloaded. The
 * scaffolder can only fetch tags, which is why the overlay exists at all.
 */

require_once dirname(__DIR__).'/replacements.php';

// ─────────────────────────────────────────────────────────────────────────────
// Options
// ─────────────────────────────────────────────────────────────────────────────

$options = getopt('', ['url:', 'slug::', 'source::', 'expect-blocks::']);

if (! isset($options['url'])) {
    fwrite(STDERR, "\nUsage: php bin/ci/check-install.php --url=<site url> [--slug=ci-theme] [--source=<theme repo>] [--expect-blocks=yes|no]\n\n");
    exit(2);
}

$baseUrl = rtrim((string) $options['url'], '/');
$slug = (string) ($options['slug'] ?? 'ci-theme');
$source = isset($options['source']) ? rtrim((string) $options['source'], '/') : null;

// Whether this framework discovers blocks from resources/views/blocks.
//
// Measured, not assumed: on framework 13.4 the theme's BlocksServiceProvider
// boots before WordPress has defined register_block_type(), so
// BlockRegistrar::registerDirectory() returns early and the blocks never
// arrive. The caller says which behaviour it expects rather than the script
// skipping when it does not find them — a check that quietly turns itself off
// is how a broken fix shipped behind a green suite.
$expectBlocks = ($options['expect-blocks'] ?? 'yes') !== 'no';

$host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
$disposable = str_ends_with($host, '.ddev.site')
    || str_ends_with($host, '.test')
    || str_ends_with($host, '.localhost')
    || in_array($host, ['localhost', '127.0.0.1'], true);

// Activating a theme replaces what visitors see. Two independent gates, the
// same shape as the skeleton's install scenarios.
if (! $disposable) {
    fwrite(STDERR, "\n\033[31mRefusing to run against {$host}.\033[0m\nThis script activates a theme; it only runs against a local or CI host.\n\n");
    exit(2);
}

// ─────────────────────────────────────────────────────────────────────────────
// Harness
// ─────────────────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n\033[1m── {$title} ──\033[0m\n";
}

/** @param callable():(true|string) $fn */
function test(string $name, callable $fn): void
{
    global $passed, $failed;

    try {
        $result = $fn();
    } catch (\Throwable $e) {
        echo "  \033[31m✗\033[0m  {$name} — {$e->getMessage()}\n";
        $failed++;

        return;
    }

    if ($result === true) {
        echo "  \033[32m✓\033[0m  {$name}\n";
        $passed++;

        return;
    }

    $reason = is_string($result) && $result !== '' ? " — {$result}" : '';
    echo "  \033[31m✗\033[0m  {$name}{$reason}\n";
    $failed++;
}

/** @return array{code: int, out: string} */
function run(string $command): array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command.' 2>&1', $descriptors, $pipes);

    if (! is_resource($process)) {
        throw new \RuntimeException("could not run: {$command}");
    }

    $out = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'out' => trim($out)];
}

/** Run a wp-cli command, raising whatever it printed if it failed. */
function wpOrFail(string $arguments): string
{
    $result = run('wp '.$arguments);

    if ($result['code'] !== 0) {
        throw new \RuntimeException("wp {$arguments} failed: {$result['out']}");
    }

    return $result['out'];
}

function wpEval(string $php): string
{
    return wpOrFail('eval '.escapeshellarg($php));
}

/** @return array{status: int, body: string} */
function http(string $url): array
{
    $context = stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 30, 'follow_location' => 1],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
            $status = (int) $m[1];
        }
    }

    return ['status' => $status, 'body' => is_string($body) ? $body : ''];
}

// ─────────────────────────────────────────────────────────────────────────────
// Scaffold
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The command changed name in 13.32; both spellings have to be tried because
 * the point of this script is to run on the old framework too.
 */
function makeThemeCommand(): string
{
    $list = run('php artisan list --raw');

    return str_contains($list['out'], 'pollora:make:theme') ? 'pollora:make:theme' : 'pollora:make-theme';
}

/**
 * Copy the commit under test over the generated theme.
 *
 * The scaffolder downloads a tag — it has no way to fetch a branch — so
 * without this, CI on a pull request measures the last release and reports it
 * as the branch being green.
 */
function overlaySource(string $source, string $themeDir, string $slug): void
{
    $replacements = scaffolderReplacements($slug);
    $skip = ['.git', 'node_modules', 'bin', '.github', 'package-lock.json', 'yarn.lock'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! in_array($file->getFilename(), $skip, true)
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copied = 0;

    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($source) + 1);
        $target = $themeDir.'/'.$relative;

        if ($file->isDir()) {
            is_dir($target) || mkdir($target, 0755, true);

            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        // Binary files carry no placeholders and must not be rewritten.
        if (preg_match('//u', $contents) === 1) {
            $contents = str_replace(array_keys($replacements), array_values($replacements), $contents);
        }

        file_put_contents($target, $contents);
        $copied++;
    }

    echo "  \033[2m→ overlaid {$copied} files from the commit under test\033[0m\n";
}

echo "\n\033[1m=== theme-default — install check ===\033[0m\n";
echo "\033[2m{$baseUrl} · theme {$slug}\033[0m\n";

$command = makeThemeCommand();

// --force only started overriding an existing directory in 13.32; before that
// the command reported "already exists" and stopped, so a re-run on the same
// site — which is what CI does when it retries — never got past the scaffold.
$existing = getcwd().'/themes/'.$slug;

if (is_dir($existing)) {
    echo "  \033[2m→ removing the previous {$slug}\033[0m\n";
    run('rm -rf '.escapeshellarg($existing));
}

echo "  \033[2m→ scaffolding with {$command}\033[0m\n";

$scaffold = run('php artisan '.$command.' '.escapeshellarg($slug)
    .' --theme-author=Pollora --theme-description='.escapeshellarg('Theme under test')
    .' --theme-version=1.0.0 --force --no-interaction');

if ($scaffold['code'] !== 0) {
    fwrite(STDERR, "\n\033[31mScaffolding failed:\033[0m\n{$scaffold['out']}\n\n");
    exit(1);
}

$themeDir = getcwd().'/themes/'.$slug;

if (! is_dir($themeDir)) {
    fwrite(STDERR, "\n\033[31mThe scaffolder reported success but {$themeDir} does not exist.\033[0m\n\n");
    exit(1);
}

if ($source !== null) {
    overlaySource($source, $themeDir, $slug);

    if (is_file($themeDir.'/package.json')) {
        echo "  \033[2m→ rebuilding assets\033[0m\n";
        run('cd '.escapeshellarg($themeDir).' && npm install --no-audit --no-fund && npm run build');
    }
}

$activated = run('wp theme activate '.escapeshellarg($slug));

if ($activated['code'] !== 0) {
    fwrite(STDERR, "\n\033[31mCould not activate the theme:\033[0m\n{$activated['out']}\n\n");
    exit(1);
}

run('wp rewrite flush --hard');

// ─────────────────────────────────────────────────────────────────────────────
// Checks
// ─────────────────────────────────────────────────────────────────────────────

section('Registration — the guard in functions.php, on the framework this site runs');

test('Loading the theme does not fatal', function () {
    $result = run('wp eval '.escapeshellarg('echo "loaded";'));

    if ($result['code'] !== 0) {
        return "WordPress could not boot with the theme active: {$result['out']}";
    }

    return str_contains($result['out'], 'loaded')
        ? true
        : "unexpected output from a booted site: {$result['out']}";
});

test('wp_get_theme() finds the active theme', function () use ($slug) {
    $stylesheet = wpEval('echo get_stylesheet();');

    if ($stylesheet !== $slug) {
        return "the active stylesheet is {$stylesheet}, not {$slug}";
    }

    return wpEval('echo wp_get_theme()->exists() ? "yes" : "no";') === 'yes'
        ? true
        : 'wp_get_theme() cannot find it — the admin will report the theme missing';
});

test('The theme is registered with Pollora', function () use ($slug) {
    // Registration is what functions.php guards. It shows up as the theme's
    // views being resolvable by name: nothing else adds that namespace.
    $resolved = wpEval('echo view()->exists("index") ? "yes" : "no";');

    return $resolved === 'yes'
        ? true
        : "the theme's views do not resolve — functions.php did not register {$slug} with this framework";
});

section('Rendering — every path this framework uses to reach a template');

$pages = [
    'Homepage' => '/',
    'A single post' => null,
    'A page' => null,
    'An unknown URL' => '/no-such-url-'.bin2hex(random_bytes(4)),
];

$postUrl = wpEval('$p = get_posts(["numberposts" => 1]); echo $p ? get_permalink($p[0]) : "";');
$pageUrl = wpEval('$p = get_posts(["post_type" => "page", "numberposts" => 1]); echo $p ? get_permalink($p[0]) : "";');

$pages['A single post'] = $postUrl;
$pages['A page'] = $pageUrl;

foreach ($pages as $label => $url) {
    $expected = $label === 'An unknown URL' ? 404 : 200;

    test("{$label} answers {$expected} with a real document", function () use ($url, $baseUrl, $expected, $label) {
        if ($url === null || $url === '') {
            return "no URL to request — the fixture this check needs is missing, so it would pass on an empty site";
        }

        $absolute = str_starts_with($url, 'http') ? $url : $baseUrl.$url;
        $response = http($absolute);

        if ($response['status'] !== $expected) {
            return "{$absolute} answered {$response['status']}";
        }

        $bytes = strlen(trim($response['body']));

        if ($bytes === 0) {
            return "{$absolute} answered {$expected} with an empty body";
        }

        if (! str_contains($response['body'], '</html>')) {
            return "{$absolute} answered {$bytes} bytes that are not a document";
        }

        // A Blade directive in the output means the template was served as a
        // file rather than rendered.
        foreach (['@extends', '@section(', '{{ $'] as $leak) {
            if (str_contains($response['body'], $leak)) {
                return "{$absolute} served the Blade source: found {$leak}";
            }
        }

        return true;
    });
}

test('No Laravel error page is served anywhere', function () use ($pages, $baseUrl) {
    foreach ($pages as $label => $url) {
        if ($url === null || $url === '') {
            continue;
        }

        $absolute = str_starts_with($url, 'http') ? $url : $baseUrl.$url;
        $body = http($absolute)['body'];

        foreach (['Whoops', 'ViewException', 'Stack trace'] as $leak) {
            if (str_contains($body, $leak)) {
                return "{$label} leaked a Laravel error page: {$leak}";
            }
        }
    }

    return true;
});

section('Blocks — named after the generated theme, not after this template');

$manifests = glob($themeDir.'/resources/views/blocks/*/block.json') ?: [];

// This one holds on every framework. It is the check that fails on the defect
// v1.4.0 shipped, where every generated theme registered its blocks under the
// template's own development name.
test('Every block is namespaced under the generated theme', function () use ($slug, $manifests) {
    if ($manifests === []) {
        return 'no block.json reached the generated theme — this check would pass on an empty directory';
    }

    $wrong = [];

    foreach ($manifests as $manifest) {
        $name = json_decode((string) file_get_contents($manifest), true)['name'] ?? '';

        if (! str_starts_with((string) $name, $slug.'/')) {
            $wrong[] = "{$name} is not namespaced under {$slug}";
        }
    }

    return $wrong === [] ? true : implode(', ', $wrong);
});

test(
    $expectBlocks
        ? 'Every block reaches the WordPress block registry'
        : 'No block reaches the registry — this framework cannot discover them',
    function () use ($manifests, $expectBlocks, $slug) {
        if ($manifests === []) {
            return 'no block.json reached the generated theme';
        }

        $registered = explode(',', wpEval(
            'echo implode(",", array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()));'
        ));

        $found = [];
        $absent = [];

        foreach ($manifests as $manifest) {
            $name = (string) (json_decode((string) file_get_contents($manifest), true)['name'] ?? '');

            in_array($name, $registered, true) ? $found[] = $name : $absent[] = $name;
        }

        if ($expectBlocks) {
            return $absent === []
                ? true
                : implode(', ', $absent).' did not reach the registry';
        }

        // Asserted in the negative on purpose. Framework 13.4 boots the
        // theme's providers before WordPress defines register_block_type(),
        // so registerDirectory() returns early. Recording that as an
        // expectation means the day it changes — a fix, a backport — this
        // says so, instead of a skip quietly passing either way.
        return $found === []
            ? true
            : implode(', ', $found)." now registers on this framework; drop --expect-blocks=no for {$slug}";
    }
);

// ─────────────────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n";
echo $failed === 0
    ? "\033[32m{$total} checks, all passed.\033[0m\n\n"
    : "\033[31m{$total} checks, {$failed} failed.\033[0m\n\n";

exit($failed === 0 ? 0 : 1);
