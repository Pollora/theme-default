<?php

declare(strict_types=1);

/**
 * Minimal assertion harness for the theme's contract tests.
 *
 * Deliberately the same shape as the skeleton's `tests/install/lib.php`, so a
 * failure reads the same way in both repositories. It is kept dependency-free:
 * these tests run on a bare `php` with no Composer install, which is what makes
 * them cheap enough to run on every push.
 *
 * A check returns true (pass), or a string explaining the failure. There is no
 * skip: a check that cannot fail is the defect this suite exists to prevent.
 */

final class Results
{
    public static int $passed = 0;

    public static int $failed = 0;
}

function section(string $title): void
{
    echo "\n\033[1m── {$title} ──\033[0m\n";
}

/**
 * Run one assertion.
 *
 * @param  callable():(true|string)  $fn
 */
function test(string $name, callable $fn): void
{
    try {
        $result = $fn();
    } catch (\Throwable $e) {
        echo "  \033[31m✗\033[0m  {$name} — {$e->getMessage()}\n";
        Results::$failed++;

        return;
    }

    if ($result === true) {
        echo "  \033[32m✓\033[0m  {$name}\n";
        Results::$passed++;

        return;
    }

    $reason = is_string($result) && $result !== '' ? " — {$result}" : '';
    echo "  \033[31m✗\033[0m  {$name}{$reason}\n";
    Results::$failed++;
}

function summary(): int
{
    $total = Results::$passed + Results::$failed;

    echo "\n";
    echo Results::$failed === 0
        ? "\033[32m{$total} checks, all passed.\033[0m\n\n"
        : "\033[31m{$total} checks, ".Results::$failed." failed.\033[0m\n\n";

    return Results::$failed === 0 ? 0 : 1;
}

function themePath(string $relative = ''): string
{
    $root = dirname(__DIR__, 2);

    return $relative === '' ? $root : $root.'/'.ltrim($relative, '/');
}

/**
 * Run a PHP snippet in its own process and return its trimmed output.
 *
 * Every registration scenario has to start from a clean global state:
 * `function_exists('pollora_register')` cannot be unset once the harness has
 * declared it, so each case gets its own interpreter. The exit code is
 * returned alongside the output because a fatal is the failure this suite is
 * built to catch, and a fatal shows up as a non-zero code.
 *
 * @return array{code: int, out: string}
 */
function phpRun(string $code): array
{
    $file = tempnam(sys_get_temp_dir(), 'theme-contract-').'.php';
    file_put_contents($file, "<?php\n".$code);

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open(
        escapeshellarg(PHP_BINARY).' -d error_reporting=E_ALL -d display_errors=1 '.escapeshellarg($file).' 2>&1',
        $descriptors,
        $pipes
    );

    if (! is_resource($process)) {
        unlink($file);

        throw new \RuntimeException('could not start a PHP process');
    }

    $out = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    unlink($file);

    return ['code' => $code, 'out' => trim($out)];
}
