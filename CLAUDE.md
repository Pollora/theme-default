# CLAUDE.md

This file provides guidance to Claude Code when working on this Pollora theme.

## Theme Structure

```
app/Providers/          # Auto-discovered service providers
config/                 # Theme config (menus, supports, sidebars, templates)
resources/
├── assets/
│   ├── css/app.css     # Tailwind CSS entry point with @theme tokens
│   ├── app.js          # JS entry point
│   ├── fonts/          # Web fonts (Inter)
│   └── images/         # Static images (logo, icons)
└── views/
    ├── layouts/app.blade.php   # Base layout (header, footer, wp_head/wp_footer)
    ├── home.blade.php          # Homepage (hero, features, latest posts)
    ├── page.blade.php          # WordPress pages
    ├── post.blade.php          # Single posts
    ├── parts/                  # Reusable partials (content, search)
    ├── errors/                 # Error pages (404)
    └── patterns/               # Gutenberg patterns
```

## Blade Directives (Sage Directives)

Use [Sage Directives](https://log1x.github.io/sage-directives-docs/) for WordPress data in templates:

```blade
{{-- Looping through posts --}}
@query(['post_type' => 'post', 'posts_per_page' => 4])
@hasposts
    @posts
        <h2>@title</h2>
        <p>@excerpt</p>
        <a href="@permalink">Read more</a>
        <time>@published('F j, Y')</time>
    @endposts
@endhasposts

{{-- Single post/page content --}}
@title              {{-- get_the_title() --}}
@content            {{-- the_content() --}}
@excerpt            {{-- the_excerpt() --}}
@permalink          {{-- get_permalink() --}}
@published('c')     {{-- get_the_date() with format --}}
@thumbnail          {{-- the_post_thumbnail() --}}
@author             {{-- get_the_author() --}}
```

For post ID, use `get_the_ID()` directly.

## CSS & Tailwind

The theme uses Tailwind CSS v4 with custom design tokens defined in `resources/assets/css/app.css` via `@theme`:

```css
@theme {
    --color-primary: var(--wp--preset--color--primary, #ff5334);
    --color-foreground: var(--wp--preset--color--foreground, #1d142a);
    /* ... */
}
```

These tokens bridge WordPress `theme.json` colors with Tailwind utility classes (`text-primary`, `bg-foreground`, etc.).

## theme.json

`theme.json` defines the WordPress block editor design system:
- **Color palette** — registered as `--wp--preset--color--*` CSS custom properties
- **Typography** — font families, sizes, heading styles
- **Link styles** — use `wp-element-button` class on `<a>` tags that should NOT receive WP link styles (color, underline)
- **Spacing & layout** — content/wide sizes

When modifying colors: update both `theme.json` (for Gutenberg) and `css/app.css` `@theme` (for Tailwind).

After modifying `theme.json`, flush WP transients: `ddev wp transient delete --all`

## Asset Building

```bash
npm run dev     # Vite dev server with HMR
npm run build   # Production build
```

Build output: `public/build/theme/{theme-name}/`

The `AssetServiceProvider` enqueues `app.js` (which imports `css/app.css`) via Pollora's Asset facade with Vite integration.

## WordPress Block Styles

Content blocks (paragraphs, blockquotes, headings, lists) are styled via `.entry-content` CSS rules in `app.css`, not Tailwind `prose`. This gives full control over WordPress block markup.

## Menu System

The `MenuServiceProvider` provides a `$menu` variable to all views:
- If a WordPress menu is assigned to the `primary` location, it's used
- Otherwise, a fallback menu is generated from published pages (with Home as first item)