<p align="center">
  <a href="https://pollora.dev">
    <img src="https://raw.githubusercontent.com/Pollora/.github/main/brand/banners/theme-default.png" width="100%" alt="Pollora Default Theme: the Blade starter theme for Pollora">
  </a>
</p>

<p align="center">
  <a href="https://github.com/Pollora/theme-default/tags"><img src="https://img.shields.io/github/v/tag/Pollora/theme-default?label=version" alt="Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/theme-default" alt="License"></a>
</p>

The starter theme every [Pollora](https://pollora.dev) project begins with: Blade templates, Vite with hot reload, Tailwind CSS v4 and a block editor styled from the same design tokens as the page. It is the template `php artisan pollora:make:theme` downloads by default, so a new theme starts from working code instead of an empty folder.

<p align="center">
  <img src="https://pollora.dev/press/theme-default.png" width="100%" alt="The Pollora default theme">
</p>

## Installation

A new Pollora project generates this theme for you. In an existing project, run:

```bash
php artisan pollora:make:theme my-theme
```

Choose "Default" when asked for a template (or pass `--repository=Pollora/theme-default`). The command downloads the latest tag, fills in the theme's name and namespace, runs `npm install` and `npm run build`, and offers to activate the theme.

Requirements: a Pollora project (PHP 8.4+ and WordPress 7.1+ for a new one) and Node.js 20.19+ or 22.12+ (Vite 8) for the asset build.

## Quick start

```bash
cd themes/my-theme
npm run dev     # Vite dev server with hot reload
npm run build   # production assets
```

Templates live in `resources/views` (`index`, `home`, `single`, `page`, `404`…), Gutenberg blocks in `resources/views/blocks` (`hero`, `call-to-action`), and theme settings in `config/` (menus, sidebars, supports, image sizes).

## Design tokens

The design lives in the `@theme static` block of `resources/assets/css/app.css`:
colors, type scale and radii, with concrete values. `npm run build` writes
them into the `theme.json` the editor reads
(`public/build/theme/<slug>/assets/theme.json`), so the page and the editor
always offer the same palette and sizes.

- Change a token in `app.css`, not in `theme.json`: the root `theme.json` is
  only the base (layout, fonts, block styles), and a slug defined there wins
  over `@theme`.
- The font is the exception: Inter (the variable font, `InterVariable.woff2`) needs a `fontFace` declaration, which only
  `theme.json` can hold, so fonts are declared there and `vite.config.js` turns
  their generation off (`disableTailwindFonts`). The same option exists for
  colors, font sizes and radii.
- Spacing and layout widths are not generated: set them in `theme.json`.

See [Theme.json and Vite Build Integration](https://pollora.dev/theming/theme-structure/)
for the details.

## Gutenberg design system

Every core block is styled in `theme.json` (`styles`: root, elements, blocks),
from the presets above only, so a paragraph, a quote, a table or a button look
the same in the editor and on the page. Block-specific touches that
`theme.json` has no property for (the quote's gradient border, the table's
rules) sit in each block's `css` field, which the editor loads too.

- Reference presets by the name WordPress prints: `5xl` becomes
  `var(--wp--preset--font-size--5-xl)`. Never a Tailwind variable
  (`var(--text-xl)`): it does not exist in the editor.
- A block's `css` takes one selector per rule; WordPress wraps it in
  `:root :where(...)` and breaks on `a, b`.
- `app/Cms/StyleLayers.php` puts WordPress's CSS in cascade layers, declared at
  the top of `app.css`: `theme, base, wp-core, wp, components, utilities`. The
  block library and the global styles beat Tailwind's reset, and a Tailwind
  class in a Blade template always beats them. A link in the templates that
  should not look like a content link says so with `no-underline`.
- `php bin/tests/run.php` checks that every preset and custom variable the
  styles use exists.

## Documentation

- [Themes](https://pollora.dev/theming/theme-structure/): generating a theme, its structure, `theme.json` and the Vite build
- [Assets and Vite](https://pollora.dev/theming/assets-vite/)
- [Gutenberg blocks](https://pollora.dev/blocks/gutenberg-blocks/)

## Template development

This repository is a template: its files carry placeholders that `pollora:make:theme` substitutes, so it does not run as is. Develop in a Pollora test project under the code name **`pollora-starter`**, unique enough that packaging cannot replace anything by accident.

1. Generate the dev theme in your test project:

   ```bash
   php artisan pollora:make:theme pollora-starter \
       --repository=Pollora/theme-default \
       --theme-author="Pollora" \
       --theme-author-uri="https://pollora.dev" \
       --theme-uri="https://pollora.dev" \
       --theme-description="Pollora starter theme" \
       --theme-version="1.0.0"
   ```

2. Develop in `themes/pollora-starter/`.

3. Package the changes back into this repository, then review them:

   ```bash
   ./bin/package-theme.sh /path/to/your-project/themes/pollora-starter
   git diff
   ```

4. Verify by regenerating the theme from the new tag once it is published:

   ```bash
   rm -rf themes/pollora-starter
   php artisan pollora:make:theme pollora-starter --repository=Pollora/theme-default
   ```

### Placeholders

`bin/package-theme.sh` turns the dev values into these placeholders, and `pollora:make:theme` replaces them:

| Placeholder | Replaced with |
|---|---|
| <code>&#37;theme_name%</code> | Theme slug (e.g. `my-theme`) |
| <code>&#37;theme_namespace%</code> | PSR-4 namespace (e.g. `Theme\MyTheme`) |
| <code>&#37;theme_uri%</code> | Theme URL |
| <code>&#37;theme_author%</code> | Author name |
| <code>&#37;theme_author_uri%</code> | Author URL |
| <code>&#37;theme_description%</code> | Theme description |
| <code>&#37;theme_version%</code> | Version number |

- Always use `pollora-starter` as the dev theme name: the packaging script depends on it, and fails if the code name survives anywhere.
- `bin/` is removed when `pollora:make:theme` downloads the theme. It holds the packaging script and the CI checks (`php bin/tests/run.php`).

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/Pollora/.github/blob/main/CONTRIBUTING.md). Report security issues privately, as described in the [security policy](https://github.com/Pollora/.github/blob/main/SECURITY.md).

## License

The Pollora Default Theme is open-source software licensed under the [MIT license](LICENSE). © [RuBee group](https://rubee.group)
