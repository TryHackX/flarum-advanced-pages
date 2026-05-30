# Advanced Pages for Flarum

[![Flarum 2.x](https://img.shields.io/badge/Flarum-2.x-orange)](https://flarum.org)
[![Packagist](https://img.shields.io/packagist/v/tryhackx/flarum-advanced-pages)](https://packagist.org/packages/tryhackx/flarum-advanced-pages)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Create advanced custom pages with **HTML**, **BBCode**, **Markdown**, **PHP**,
or **plain text** content types for your Flarum 2.x forum. A powerful
alternative to `fof/pages` with multi-format support, live preview,
formatting toolbars, and granular access control.

> **Latest highlights:**
> - **"View spoiler content" defaults to Members** — a new migration
>   grants `advancedPages.viewSpoilers` to the Members group on install,
>   so the permission row in the admin grid is correctly defaulted
>   rather than being implicitly admin-only.
> - **Reset Settings button in the admin panel** — `AdvancedPagesPage`
>   is a custom admin page, so Flarum didn't auto-render the standard
>   *Reset Settings* button next to *Save Changes*. It now sits in the
>   familiar place (wrapped in `Form-group Form-controls`, danger
>   colour like other extensions) and reverts this extension's
>   settings via Flarum core's `ResetExtensionSettingsModal`.
> - **Cancel button in the "Reset extension settings" modal** uses
>   Flarum's standard `Button--inverted` style now (was a plain
>   borderless button under dark themes). Applied via a small
>   `MutationObserver` that tags the button when the modal appears.
>   Each TryHackX extension registers this independently in its admin
>   bundle, so the fix follows whichever extension is installed.

> **Note:** Recent updates target the **2.x** line only. There is no
> 1.x line for this extension (Advanced Pages is Flarum 2.x only).

## Features

- **5 content types** — HTML (HTMLPurifier sanitised), BBCode, Markdown,
  PHP (sandboxed), Plain Text.
- **Formatting toolbars** — context-aware buttons for the BBCode and
  Markdown editors.
- **Live preview** — Raw / Preview toggle with syntax highlighting
  (highlight.js One Dark).
- **Code editor** — CodeMirror-powered editor for HTML and PHP with
  syntax highlighting.
- **BBCode extensions** — `[table]`, `[spoiler]`, `[center]`, extended
  `[url]` parser (configurable).
- **Newline modes** — Flarum vanilla or *preserve-all* for BBCode pages.
- **Spoiler system** — `[spoiler]` / `[spoiler=Title]` with
  permission-based visibility (`advancedPages.viewSpoilers`, default
  Members).
- **Admin panel** — full CRUD with pagination, per-page selector,
  inline settings, *Save Changes* + *Reset Settings* buttons (Reset
  opens Flarum core's `ResetExtensionSettingsModal` populated with
  this extension's settings).
- **SEO support** — meta descriptions and proper `<title>` tags.
- **Access control** — publish, hide (admin-only), restrict
  (login-required), per-group visibility.
- **Custom permissions** — manage pages, view spoiler content.
- **Clean URLs** — pages live at `/p/{slug}`.

## Screenshots

![Mobile view of the discussion list across multiple TryHackX layout combinations](assets/ALL_MOBILE.png)

*Mobile view — discussion list rendered with different combinations of TryHackX extensions (thumbnails + ratings + views, thumbnails + views, thumbnails only, ratings only, views only, vanilla Flarum).*

![Advanced Pages admin settings — page list, BBCode toggles, forum integration and permissions](assets/Advanced_Pages.png)

*Advanced Pages admin panel — page management, BBCode tag toggles (`[table]`, `[spoiler]`, `[center]`, extended `[url]`), forum spoiler replacement and the *Save Changes* / *Reset Settings* row, plus the *Manage Advanced Pages* and *View spoiler content* permissions.*

![Desktop discussion list with the full TryHackX stack — thumbnail sliders, star ratings and the magnet button](assets/ALL_VIA_MAGNETS.png)

*Desktop discussion list with the full TryHackX stack — thumbnail sliders on the left, star ratings on the right, magnet button next to each topic.*

![Desktop discussion list — magnet tooltip mid-load on a topic](assets/ALL_VIA_MAGNETS_v2.png)

*Desktop discussion list — hover state showing the magnet tooltip loading inline (powered by `tryhackx/flarum-magnet-link`).*

## Support Development

If you find this extension useful, consider supporting its development:

- **Monero (XMR):** `45hvee4Jv7qeAm6SrBzXb9YVjb8DkHtFtFh7qkDMxS9zYX3NRi1dV27MtSdVC5X8T1YVoiG8XFiJkh4p9UncqWGxHi4tiwk`
- **Bitcoin (BTC):** `bc1qncavcek4kknpvykedxas8kxash9kdng990qed2`
- **Ethereum (ETH):** `0xa3d38d5Cf202598dd782C611e9F43f342C967cF5`

You can also find the donation option in the extension's admin settings panel.

## Requirements

- **Flarum** `^2.0`
- **PHP** `^8.1`
- **PHP memory_limit** `256M` minimum (512M+ recommended — see *Memory* below).

## Installation

```bash
composer require tryhackx/flarum-advanced-pages
php flarum migrate
php flarum cache:clear
```

## Updating

```bash
composer update tryhackx/flarum-advanced-pages
php flarum migrate
php flarum cache:clear
```

## Usage

### Creating pages

1. Go to **Admin Panel → Advanced Pages**.
2. Click **Create Page**.
3. Choose a content type and write your content.
4. Configure visibility (published, hidden, restricted, group access).
5. Save — the page is available at `/p/{your-slug}`.

### Content types

| Type | Description | Security |
| --- | --- | --- |
| **HTML** | Full HTML with styles / scripts / forms | Raw output, permission-gated |
| **BBCode** | BBCode with custom tags & toolbar | Escaped and parsed via s9e/TextFormatter |
| **Markdown** | Full Markdown with live preview | Escaped and parsed via s9e/TextFormatter |
| **PHP** | Server-side PHP execution | Admin-only, sandboxed, errors logged never shown |
| **Plain Text** | Auto-escaped text with URL linking | Fully escaped output |

### BBCode toggles

| Setting | Tags | Default |
| --- | --- | --- |
| Tables | `[table]` `[tr]` `[th]` `[td]` | On |
| Spoiler | `[spoiler]` `[spoiler=Title]` | On |
| Center | `[center]` | On |
| Extended URL | `[url]` (accepts URLs Flarum rejects) | Off |
| **Replace Forum Spoiler** | swap Flarum's default `[spoiler]` for the Advanced Pages `details/summary` style across all posts | Off |

After toggling BBCode settings, clear the formatter cache:

```bash
php flarum cache:clear
```

### Newline mode (BBCode)

Per-page newline behaviour:

- **Flarum** *(default)* — multiple newlines collapse to a single break
  (vanilla Flarum behaviour).
- **Preserve** — all newlines become `<br>` tags.

### PHP pages

PHP pages execute in a sandboxed scope with access to:

- `$page` — current Page model
- `$actor` — current user (or `null` for guests)
- `$settings` — Flarum SettingsRepository

```php
<h1>Welcome, <?= htmlspecialchars($actor ? $actor->display_name : 'Guest') ?></h1>
<p>Current time: <?= date('Y-m-d H:i:s') ?></p>
```

PHP errors are written to Flarum's log but never shown to visitors.

### Page visibility

| Option | Description |
| --- | --- |
| **Published** | Accessible to permitted users. |
| **Draft** | Page exists but is not accessible. |
| **Hidden** | Only visible to administrators. |
| **Restricted** | Requires login to view. |
| **Group access** | Restrict to specific user groups. |

### Permissions

| Permission | Section | Default | Description |
| --- | --- | --- | --- |
| Manage Advanced Pages | Moderate | Mods | Create, edit, delete pages. |
| View spoiler content | View | **Members** | See spoiler content on pages (default set by migration on install / enable). |

## Memory requirements

Flarum compiles all extension LESS styles together. If you get
`PHP Fatal error: Allowed memory size exhausted`:

1. Set `memory_limit` to at least `256M` in `php.ini`
   (`512M`+ recommended).
2. **WAMP users**: Apache with `mod_fcgid` uses the `php.ini` in the
   Apache bin directory, not the PHP directory.
3. Restart Apache after changes.

## Security

- HTML pages are rendered raw (full HTML / CSS / JS) — page creation is
  permission-gated.
- PHP execution is sandboxed in an isolated closure with custom error
  handling.
- PHP errors are never exposed to end users.
- Only admins can create PHP pages.
- The raw `content` field is hidden from non-admin API responses.
- URL-scheme blocking (`javascript:`, `data:`, `vbscript:`) in the
  extended URL parser.
- Spoiler content is stripped server-side for users without the
  `advancedPages.viewSpoilers` permission.

## Links

- [GitHub](https://github.com/TryHackX/flarum-advanced-pages)
- [Packagist](https://packagist.org/packages/tryhackx/flarum-advanced-pages)
- [Report Issues](https://github.com/TryHackX/flarum-advanced-pages/issues)

## License

MIT License. See [LICENSE](LICENSE) for details.
