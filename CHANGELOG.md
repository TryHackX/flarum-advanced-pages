# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Render cache for Markdown and BBCode pages.** The expensive s9e
  parse+render step is now memoised in Flarum's file cache. Cache keys derive
  from the page content, so editing a page produces a fresh render
  automatically, and `php flarum cache:clear` (already required after changing
  BBCode/formatter settings) wipes the entries. PHP pages are never cached
  (their output is request-dependent); the actor-dependent spoiler gating and
  the `[url]` post-processing still run per request, outside the cache.

### Changed
- The raw `content` field is now also exposed to holders of
  `advancedPages.manage` (page managers), not only administrators — so an
  API-driven edit can read the current content instead of unintentionally
  blanking it. Everyone else still receives only the rendered output.
- The *Allow script execution* help text and the README *Security* section now
  state plainly that the toggle gates `<script>` tags only and is **not** a
  sandbox: raw HTML can still run JavaScript via other vectors (inline
  event-handler attributes such as `onerror`), so HTML-page creators should be
  treated as able to run JS regardless of the toggle.

### Fixed
- **Nested spoilers no longer leak.** `hideSpoilerContent()` now finds the
  matching close of each outermost spoiler with a depth-aware scan instead of a
  non-greedy regex, which previously stopped at the first `</details>` and
  leaked the remainder of an outer spoiler that contained a nested one (and
  produced malformed markup). Non-spoiler HTML is left byte-for-byte untouched.
- A page whose slug is all digits (e.g. `2024`) is now reachable through the
  Show endpoint: `find()` falls back to a slug lookup when no page has that id.
- The editor's `<select>` menus (Parent Page, Content Type, Newline Mode) no
  longer clip the descenders of letters — Flarum's fixed `.FormControl`
  `height: 36px` is relaxed to `height: auto` (with a `min-height`) for selects
  inside the modal.

### Chore
- Deduplicated `.gitignore` (doubled `vendor/` and `node_modules/`, redundant
  `.DS_Store`, and the self-referential `.gitignore` entry that kept the file
  itself untracked).

## [2.1.0] - 2026-06-10

### Added
- **Nested / child pages.** Pages can have a parent, forming a tree. The editor
  has a *Parent Page* selector, the admin list shows the hierarchy as an indented
  tree, and pages render breadcrumbs from the parent chain. The hierarchy is the
  organisation (tree / order / breadcrumbs); the slug is the independent URL and
  may still be a slash path (e.g. `/p/docs/getting-started`).
- **Drag-and-drop ordering & nesting.** Each admin row has a grip handle (`⋮⋮`)
  at the end of the Actions column. Drag a page **onto** another to nest it under
  that page, or **between** rows to reorder — with live drop indicators and
  animations. Order persists (`position`) per parent. A page can't be dropped
  into its own subtree.
- **Per-tree breadcrumb CSS.** On a root page (no parent), tick *Custom
  breadcrumbs CSS?* to style — or hide (`display:none`) — the breadcrumbs for
  that whole tree (targets `.AdvancedPages-breadcrumbs`).
- **Per-content-type creation permissions** — `advancedPages.create.text`,
  `.bbcode`, `.markdown`, `.html`, `.php` (admins always bypass). The safe
  types (text / BBCode / Markdown) are toggleable in the admin permission grid;
  the code/script-capable types (HTML, PHP) are intentionally *not* in the grid.
- **`php flarum advancedpages:permission` console command** — grant / revoke /
  list these permissions per group from the CLI. Granting the sensitive
  `html` / `php` / `all` permissions requires an explicit `--force` flag. This
  is the only way to delegate HTML/PHP page creation, so it can never be enabled
  by an accidental click in the admin panel.
- **Per-page "Allow script execution" toggle.** `<script>` tags embedded in page
  content now run only when this is enabled (off by default for new pages).
  Existing HTML/PHP pages are migrated with it enabled so they keep working.
- **`$actor` now works inside PHP pages** — it is the real current user (a guest
  object for guests), passed straight from the request rather than always `null`.
- **PHP pages can handle POST and file uploads.** `/p/{slug}` now also accepts
  POST, so PHP pages receive `$_POST` / `$_FILES` from forms that submit back to
  themselves (e.g. contact forms, uploaders). `$_GET` already worked. CSRF stays
  enforced: forms must submit the session token, exposed to PHP pages as the new
  `$csrfToken` variable. A live demo ships at `/p/php-request-test`.
- **`$pages` tree helper in PHP pages** — a read-only, visibility-scoped
  navigator over the page tree (`->all()`, `->roots()`, `->children()`,
  `->tree($slug)`, `->find()`, `->ancestors()`) for building nav menus, sidebars
  or sitemaps without leaking pages the viewer can't see.

### Changed
- Per-group page visibility now uses Laravel's cross-database
  `whereJsonContains()` instead of the MySQL-only `JSON_CONTAINS()`, so it works
  on SQLite 3.38+ and PostgreSQL as well as MySQL/MariaDB.
- Migrations standardised on `Flarum\Database\Migration` helpers; removed the
  MySQL-only `->after()` column modifiers.
- The admin page list now loads every page (previously only the first 20 were
  shown) and renders from the store.
- The "Reset extension settings" cancel-button styling is now pure CSS instead
  of a document-wide `MutationObserver`.

### Fixed
- **Spoiler permission gating** now works regardless of the *Replace Forum
  Spoiler* setting and no longer leaks spoiler content that contains nested
  `<div>`s. The locked-spoiler message is now translatable.
- Embedded page scripts no longer execute twice; removed the global
  `DOMContentLoaded` re-dispatch that could disrupt other extensions.
- `PagePolicy::view()` now enforces per-group visibility, matching the list scope.
- PHP render errors are logged with the real page id.

### Security
- HTML and PHP page creation are admin-only by default and can only be delegated
  through the `advancedpages:permission` console command. HTML is still rendered
  **raw** by design (see *Security* in the README) — it is not sanitised.

### Removed
- Dead code: unused `PageValidator`, `PageRepository`, and `FormattedEditor.js`.

## [2.0.4] - 2026-05-30

### Added
- **`advancedPages.viewSpoilers` default grant** — new migration
  (`2026_05_29_000000_grant_view_spoilers_to_members.php`) that grants
  *View spoiler content* to the Members group on install / enable. Uses
  `Migration::addPermissions()` so it only inserts when the grant
  doesn't already exist — it never overrides an admin's manual change.
- **Reset Settings button in the admin panel** — `AdvancedPagesPage`
  is a custom admin page (extends `ExtensionPage`), so Flarum didn't
  auto-add the standard *Reset Settings* button next to *Submit*. We
  now render it via `this.resetButton()`, matching the experience of
  the other TryHackX extensions and letting admins revert this
  extension's settings to defaults from the same place.

### Fixed
- **Cancel button in core's "Reset extension settings" modal** now
  uses Flarum's standard `Button--inverted` style so it doesn't render
  as a plain borderless button. Implemented with a small
  `MutationObserver` that adds the `Button--inverted` class to the
  Cancel button when the modal appears in the DOM (the modal class
  is lazy-loaded by core and not statically importable, so we can't
  extend its prototype directly). Each TryHackX extension registers
  this independently; repeated `classList.add` of the same class is
  a no-op.

## [2.0.2] - 2026-04-09

### Added
- *Replace Forum Spoiler* setting — replaces Flarum's default inline
  spoiler with the Advanced Pages `details/summary` style across all
  forum posts.
- *Forum Integration* settings section in the admin panel.

### Changed
- Moved support button to the top of the admin settings page.
- Removed margin-top / padding-top / border-top CSS from the support
  button section.

## [2.0.1] - Initial tracked release

### Added
- 5 content types: HTML, BBCode, Markdown, PHP, Plain Text.
- Formatting toolbars for BBCode and Markdown editors.
- Live preview with syntax highlighting.
- CodeMirror-powered editor for HTML and PHP.
- BBCode extensions: table, spoiler, center, extended URL parser.
- Configurable newline modes for BBCode pages.
- Spoiler system with permission-based visibility.
- Admin panel with full CRUD, pagination, and inline settings.
- SEO support with meta descriptions and `<title>` tags.
- Access control: publish, hide, restrict, per-group visibility.
- Custom permissions for page management and spoiler viewing.
- Clean URLs at `/p/{slug}`.
