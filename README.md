# Pollora Default Theme

The default theme template for [Pollora](https://pollora.dev). This repository contains placeholder files that are processed by `pollora:make-theme` when creating a new project.

## For End Users

You don't interact with this repository directly. When you create a Pollora project, the theme is generated automatically:

```bash
composer create-project pollora/pollora my-project
# or manually:
php artisan pollora:make-theme my-theme
```

## Design tokens

The design lives in the `@theme static` block of `resources/assets/css/app.css`:
colours, type scale and radii, with concrete values. `npm run build` writes
them into the `theme.json` the editor reads
(`public/build/theme/<slug>/assets/theme.json`), so the page and the editor
always offer the same palette and sizes.

- Change a token in `app.css`, not in `theme.json`: the root `theme.json` is
  only the base (layout, fonts, block styles), and a slug defined there wins
  over `@theme`.
- The font is the exception: Inter (the variable font, `InterVariable.woff2`) needs a `fontFace` declaration, which only
  `theme.json` can hold, so fonts are declared there and `vite.config.js` turns
  their generation off (`disableTailwindFonts`). The same option exists for
  colours, font sizes and radii.
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

## Contributing

### Development Setup

Theme development happens in a Pollora test project using the code name **`pollora-starter`**. This name is unique enough to avoid accidental replacements during packaging.

1. **Generate the dev theme** in your test project:

```bash
php artisan pollora:make-theme pollora-starter \
    --theme-author="Pollora" \
    --theme-author-uri="https://pollora.dev" \
    --theme-uri="https://pollora.dev" \
    --theme-description="Pollora starter theme" \
    --theme-version="1.0.0"
```

2. **Develop** in `themes/pollora-starter/` — modify views, CSS, config, etc.

3. **Package** your changes back into this repository:

```bash
cd /path/to/theme-default
./bin/package-theme.sh /path/to/your-project/themes/pollora-starter
```

The script replaces all `pollora-starter` / `PolloraStarter` / `%theme_namespace%` references with the appropriate `%placeholder%` tokens.

4. **Review, commit, tag and push**:

```bash
git diff
git add -A && git commit -m "feat: description of changes"
git tag x.y.z
git push origin main --tags
```

5. **Verify** by regenerating the theme from the updated tag:

```bash
rm -rf themes/pollora-starter
php artisan pollora:make-theme pollora-starter ...
```

### Placeholders

The following placeholders are replaced by `pollora:make-theme`:

| Placeholder | Replaced with |
|---|---|
| `pollora-starter` | Theme slug (e.g. `my-theme`) |
| `%theme_namespace%` | PSR-4 namespace (e.g. `Theme\MyTheme`) |
| `https://pollora.dev` | Theme URL |
| `Pollora` | Author name |
| `https://pollora.dev` | Author URL |
| `Pollora starter theme` | Theme description |
| `1.0.0` | Version number |

### Important

- Always use `pollora-starter` as the dev theme name — the packaging script depends on it
- Never commit files with concrete theme names (check with `grep -r "pollora-starter" --include="*.php" --include="*.css"` before pushing)
- The `bin/` directory is excluded when the theme is downloaded by `pollora:make-theme`