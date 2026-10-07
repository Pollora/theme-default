# %theme_name%

%theme_description%

Built with [Pollora](https://pollora.dev) from the [theme-default](https://github.com/Pollora/theme-default) template.

## What's inside

```
%theme_name%/
├── app/                 # PHP classes, namespace %theme_namespace% (providers, CMS integrations)
├── config/              # menus, sidebars, supports, image sizes, Gutenberg, login screen
├── resources/
│   ├── assets/          # CSS (Tailwind CSS v4, design tokens in app.css), JS, fonts, images
│   └── views/           # Blade templates (index, home, single, page, 404…)
│       ├── blocks/      # Gutenberg blocks, registered automatically
│       └── patterns/    # Block patterns
├── functions.php        # Registers the theme with Pollora
├── style.css            # WordPress theme header
├── theme.json           # Base editor settings; the build adds the design tokens
└── vite.config.js       # Asset build
```

## Commands

Run from `themes/%theme_name%`:

```bash
npm run dev      # Vite dev server with hot reload
npm run build    # production assets
```

From the project root:

```bash
php artisan pollora:make:block my-block --theme=%theme_name%   # a new Gutenberg block
php artisan discovery:clear                                    # after adding attribute-based classes
php artisan pollora:doctor                                     # when something fails without an error
```

Change colors, font sizes and radii in the `@theme static` block of `resources/assets/css/app.css`, not in `theme.json`: `npm run build` writes them into the `theme.json` the editor reads.

## Read more

- [Themes](https://pollora.dev/theming/theme-structure/): structure, template hierarchy, `theme.json` and the Vite build
- [Assets and Vite](https://pollora.dev/theming/assets-vite/)
- [Gutenberg blocks](https://pollora.dev/blocks/gutenberg-blocks/)
