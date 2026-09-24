<?php

declare(strict_types=1);

/**
 * What this template owes the skeletons that scaffold it.
 *
 * `make:theme` downloads the latest tag onto whatever site asks for it, so this
 * repository ships to two different consumers at once, and a file removed for
 * one of them breaks the other silently:
 *
 *  - skeletons up to v13.4 render through `Route::wp()` and name their views
 *    literally — `view('post')`, `view('home')`. They never consult the
 *    WordPress template hierarchy, so a renamed view is a 500 on a live site.
 *  - skeletons from v13.32 let the hierarchy pick the template, which falls
 *    back to index.blade.php for everything it cannot match.
 *
 * Both contracts are checked here, by name, because that is how they are
 * consumed: by name.
 */

/**
 * The views skeletons up to v13.4 request literally, from routes/web.php:
 *
 *   Route::wp('home',   fn () => view('home'));
 *   Route::wp('single', fn () => view('post'));
 *   Route::wp('page',   fn () => view('page'));
 *   Route::wp('404',    fn () => response()->view('errors.404', [], 404));
 */
const LEGACY_ROUTE_VIEWS = ['home', 'post', 'page', 'errors/404'];

/**
 * What the WordPress template hierarchy resolves against.
 *
 * `index` is the last link in the chain: without it every archive answers 200
 * with an empty body, which is how seven page types shipped broken before
 * v13.32.0-beta.3.
 *
 * `404` is here because the hierarchy looks for that name and nothing else.
 * The theme's own not-found screen lived at errors/404 alone, so a missing
 * page rendered the generic index fallback on every modern skeleton — with
 * the right status code, which is why nothing reported it.
 */
const HIERARCHY_VIEWS = ['index', 'home', 'page', 'single', '404'];

function viewExists(string $view): bool
{
    return is_file(themePath('resources/views/'.str_replace('.', '/', $view).'.blade.php'));
}

function checkViewContract(): void
{
    section('View contract — the names both generations of skeleton ask for');

    test('Skeletons up to v13.4 find every view their routes name', function () {
        $missing = array_values(array_filter(LEGACY_ROUTE_VIEWS, fn (string $v): bool => ! viewExists($v)));

        return $missing === []
            ? true
            : 'missing '.implode(', ', $missing).' — a site on framework 13.4 that pulls this tag gets a 500';
    });

    test('The template hierarchy finds every view it falls back to', function () {
        $missing = array_values(array_filter(HIERARCHY_VIEWS, fn (string $v): bool => ! viewExists($v)));

        return $missing === []
            ? true
            : 'missing '.implode(', ', $missing).' — those page types render empty';
    });

    // The two names the not-found screen answers to. Neither can go: the
    // hierarchy will not look for errors/404, and skeletons up to v13.4 ask
    // for nothing else.
    test('Both names of the not-found screen render the same body', function () {
        $hierarchy = themePath('resources/views/404.blade.php');
        $legacy = themePath('resources/views/errors/404.blade.php');

        if (! is_file($hierarchy)) {
            return '404.blade.php is missing — a missing page will render the generic index fallback';
        }

        if (! is_file($legacy)) {
            return 'errors/404.blade.php is missing — skeletons up to v13.4 get "View [errors.404] not found"';
        }

        $shared = 'parts.not-found';

        foreach ([$hierarchy => '404', $legacy => 'errors/404'] as $path => $name) {
            if (! str_contains((string) file_get_contents($path), $shared)) {
                return "{$name} no longer includes {$shared}, so the two screens can drift apart";
            }
        }

        return true;
    });

    // post.blade.php exists only for the older skeletons. It has no place in
    // the hierarchy, so nothing else would notice it disappearing.
    test('post.blade.php still carries the note explaining why it exists', function () {
        $source = (string) file_get_contents(themePath('resources/views/post.blade.php'));

        return str_contains($source, 'v13.4')
            ? true
            : 'the file no longer says who needs it, so the next cleanup will delete it';
    });
}

/**
 * Every template a view pulls in has to be there.
 *
 * Blade resolves these at render time, so a moved partial is a 500 on the page
 * that uses it and nowhere else — the kind of thing a scaffolded site hits
 * before its author has written a line.
 */
function checkViewReferences(): void
{
    section('View references — every @extends and @include resolves');

    $views = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(themePath('resources/views'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (str_ends_with($file->getPathname(), '.blade.php')) {
            $views[] = $file->getPathname();
        }
    }

    sort($views);

    test(count($views).' Blade files reference only views that exist', function () use ($views) {
        if ($views === []) {
            return 'no Blade file was found — the walk is looking in the wrong place';
        }

        $broken = [];

        foreach ($views as $view) {
            $source = (string) file_get_contents($view);

            if (preg_match_all('/@(?:extends|include|includeIf|includeWhen|includeFirst)\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $referenced) {
                if (! viewExists($referenced)) {
                    $broken[] = basename($view)." → {$referenced}";
                }
            }
        }

        return $broken === [] ? true : implode(', ', $broken);
    });
}

/**
 * Nothing may leave this repository carrying the development code name.
 *
 * The theme is developed as `pollora-starter` and packaged into placeholders by
 * bin/package-theme.sh, whose rules are anchored on a surrounding phrase. When
 * the blocks directory arrived, no rule matched its JSON, JSX or CSS, and
 * v1.4.0 shipped 48 occurrences: every theme generated from that tag registered
 * its blocks as `pollora-starter/hero`, under the `pollora-starter` text
 * domain, styled by `.wp-block-pollora-starter-*`.
 *
 * README.md is exempt: it documents the packaging workflow and names the code
 * name on purpose.
 */
function checkNoLeakedCodeName(): void
{
    section('Packaging — no development code name survives');

    $offenders = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(themePath(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        foreach (['/.git/', '/node_modules/', '/README.md', '/bin/'] as $exempt) {
            if (str_contains($path, $exempt)) {
                continue 2;
            }
        }

        if (! $file->isFile() || ! is_readable($path)) {
            continue;
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, 'pollora-starter') || str_contains($contents, 'PolloraStarter')) {
            $offenders[] = substr($path, strlen(themePath()) + 1);
        }
    }

    sort($offenders);

    test('No file keeps the pollora-starter code name', function () use ($offenders) {
        return $offenders === []
            ? true
            : 'the code name survived packaging in '.implode(', ', $offenders);
    });

    test('The placeholders the scaffolder substitutes are still present', function () {
        $style = (string) file_get_contents(themePath('style.css'));

        $missing = array_values(array_filter(
            ['%theme_name%', '%theme_uri%', '%theme_description%', '%theme_author%', '%theme_author_uri%', '%theme_version%'],
            fn (string $token): bool => ! str_contains($style, $token)
        ));

        return $missing === []
            ? true
            : 'style.css lost '.implode(', ', $missing).' — generated themes will carry this template\'s own header';
    });

    test('Blocks name themselves after the generated theme, not the template', function () {
        $blocks = glob(themePath('resources/views/blocks/*/block.json')) ?: [];

        if ($blocks === []) {
            return 'no block.json was found — this check would pass on an empty directory';
        }

        $wrong = [];

        foreach ($blocks as $block) {
            $manifest = json_decode((string) file_get_contents($block), true);

            if (! is_array($manifest) || ! isset($manifest['name'])) {
                $wrong[] = basename(dirname($block)).': unreadable block.json';

                continue;
            }

            if (! str_starts_with((string) $manifest['name'], '%theme_name%/')) {
                $wrong[] = basename(dirname($block)).": name is {$manifest['name']}";
            }
        }

        return $wrong === [] ? true : implode(', ', $wrong);
    });
}

/**
 * The login config has to name a file that is there.
 *
 * Pollora reads `config/login.php` and inlines whatever `logo.source` points
 * at, from the theme's root. A path that does not resolve is not an error
 * anywhere: the framework falls back to WordPress's logo and the screen looks
 * almost right, which is exactly how a wrong path survives a review. Moving
 * the file, or renaming the directory it sits in, is caught here instead.
 *
 * The file is executed in its own interpreter with the two WordPress
 * functions it calls stubbed, because that is how the theme module loads it —
 * a `require`, on `init`, with WordPress up.
 */
function checkLoginConfig(): void
{
    section('Login screen — the config names files that exist');

    $path = themePath('config/login.php');

    test('config/login.php is present', fn () => is_file($path) ?: 'the theme no longer ships a login config, so its login screen is WordPress\'s');

    if (! is_file($path)) {
        return;
    }

    $result = phpRun(
        'function home_url($p = "") { return "https://example.test".$p; }'."\n"
        .'function get_bloginfo($s = "", $f = "raw") { return "Example"; }'."\n"
        .'echo json_encode(require '.var_export($path, true).');'
    );

    test('config/login.php is readable on its own', function () use ($result) {
        return $result['code'] === 0 ? true : 'it did not run: '.trim($result['out']);
    });

    if ($result['code'] !== 0) {
        return;
    }

    $config = json_decode($result['out'], true);
    $source = is_array($config) ? ($config['logo']['source'] ?? null) : null;

    test('It names a logo', fn () => is_string($source) && $source !== ''
        ? true
        : 'logo.source is missing, so the screen falls back to WordPress\'s logo without saying so');

    test('The logo it names is in the repository', function () use ($source) {
        if (! is_string($source) || $source === '') {
            return 'skipped: no logo named';
        }

        $file = themePath($source);

        return is_file($file)
            ? true
            : $source.' does not exist — the login screen would silently keep WordPress\'s logo';
    });

    test('The logo is small enough to inline', function () use ($source) {
        if (! is_string($source) || ! is_file(themePath($source))) {
            return 'skipped: no logo to measure';
        }

        // The framework refuses past 96 KB, and base64 adds a third again to
        // every login response.
        $size = (int) filesize(themePath($source));

        return $size <= 98304
            ? true
            : $source.' is '.number_format($size).' bytes; the framework will not inline it';
    });
}
