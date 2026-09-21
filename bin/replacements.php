<?php

declare(strict_types=1);

/**
 * The placeholders the scaffolder substitutes when it generates a theme.
 *
 * Mirrors MakeThemeCommand::getReplacements() in the framework. Two things
 * here need it, and neither can go and ask the framework:
 *
 *  - the contract tests, which lint what the user receives rather than the
 *    template — `namespace %theme_namespace%\Providers;` does not parse;
 *  - bin/ci/check-install.php, which overlays the commit under test onto a
 *    theme the scaffolder generated from the published tag, so CI measures the
 *    branch and not what is already released.
 *
 * Drift against the framework is caught by the contract test "No file uses a
 * placeholder the scaffolder does not substitute": a key that disappears here
 * or a token that appears in the template without a key both fail it.
 *
 * @return array<string, string>  placeholder => a plausible substituted value
 */
function scaffolderReplacements(string $slug = 'my-theme'): array
{
    $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));

    return [
        '%theme_name%' => $slug,
        '%theme_camel%' => lcfirst($studly),
        '%theme_namespace%' => 'Theme\\'.$studly,
        '%theme_author%' => 'Pollora',
        '%theme_author_uri%' => 'https://pollora.dev',
        '%theme_uri%' => 'https://pollora.dev',
        '%theme_description%' => 'Theme under test',
        '%theme_version%' => '1.0.0',
    ];
}
