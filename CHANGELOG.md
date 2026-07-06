# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.0] - 2026-07-06

### Added
- **Pin pages to the navigation menu.** A new **“Pin to navigation menu”** toggle
  in the page editor surfaces a page as a link in the forum's index-sidebar
  navigation — the same *Dropdown-menu* list that holds *All Discussions* and links
  added by other extensions (*Members*, *Badges*, …). When pinned, two optional
  fields appear: a **Menu icon** (a FontAwesome class, e.g. `fas fa-book`; blank
  falls back to a default icon) and a **Short Name** (a shorter menu label for when
  the page title is too long; blank uses the title). Each pinned link is a plain
  full-page-load link (not an in-app SPA route), so a pinned redirect forwards on
  the server straight away — no "Loading…" step — and clicking behaves the same as
  the page-list "open" link (including ⌘/Ctrl-click into a new tab). Pinned links
  are ordered exactly like the admin page list
  (parent/child tree, then per-level order) and honour every visibility rule — a
  draft, hidden, restricted or group-limited page appears in the menu only for
  viewers allowed to open it. The set rides along on the forum payload
  (`advancedPagesPinned`), computed in a single lightweight, content-free query, so
  the menu renders with no extra request. Nothing is pinned by default. *(Adds an
  `is_pinned` / `pinned_icon` / `pinned_label` migration.)*
- **New "Redirection" content type.** A redirect page stores a destination URL and
  forwards visitors there, counting the visit first. A per-page **Redirect
  immediately** toggle chooses the behaviour: on (default) issues a real HTTP 302 at
  `/p/{slug}` (handled by a dedicated `PageViewController` before the SPA loads);
  off shows the page with a live **5-second countdown** ("You will be redirected in
  N s…") above the destination link, then forwards automatically (no click required,
  via a delayed meta-refresh plus a client-side timer). The target is validated on save and wherever it is
  rendered: only an `https://`/`http://` address or a root-relative `/path` is
  accepted, so it can never carry a `javascript:`/`data:` scheme into a redirect or
  a bare href. It pairs with pinning to give **custom menu links** (internal or
  external); the pinned link points at the page's own `/p/{slug}` (not straight at
  the destination) so those clicks are counted before forwarding. Creation is gated
  by a new, grid-grantable `advancedPages.create.redirect` permission. *(Adds a
  `redirect_immediate` migration; the URL itself lives in the existing `content`.)*
- **Per-page view counter.** Every page now tracks how many times it has been
  viewed, incremented once per visit with a single atomic `view_count = view_count
  + 1` update that never touches `updated_at`. It is shown in a new **Views** column
  in the admin list (after *Groups*) and exposed to PHP pages as `$page->view_count`
  (and on every page in the `$pages` tree helper), so a page can build e.g. a
  "most viewed" list. *(Adds a `view_count` migration.)*

### Fixed
- **Saving a page no longer renders (executes) its content.** The API used to
  render `contentHtml` on every non-list response, including create/update — so
  saving a PHP page ran it, and a page that called `exit()`/`header()` aborted the
  save response with an "Oops! Something went wrong" error (the page saved anyway).
  `contentHtml` is now rendered only on reads (GET); a save just persists. (For
  redirects, use the Redirection content type rather than a PHP `header()` page —
  PHP pages are rendered inline and cannot emit HTTP headers or `exit`.)
- **The admin page list is horizontally scrollable on mobile.** On narrow screens
  the table (type, status, groups, views and action columns) overflowed the viewport
  and clipped, hiding values; it now scrolls horizontally so every column stays
  readable.
- **Action buttons in the admin list are vertically aligned.** The drag-to-reorder
  handle sat on the text baseline, a few pixels below the Edit/open buttons; the
  actions cell is now `inline-flex` + `align-items: center`, so the handle lines up
  with the rest.
- **A page with an empty `visibleGroups` array is no longer hidden from everyone.**
  The `array` cast persists `[]` as the JSON string `'[]'`, which the visibility
  scope's `whereNull` / `whereJsonContains` checks all miss — silently hiding the
  page. Saving now normalises an empty array to `NULL` (the single “no restriction”
  sentinel), and the scope additionally treats a literal `'[]'` as unrestricted so
  any legacy row stays visible (plain string equality, kept cross-database).

### Changed
- **The POST form-submission route no longer hardcodes an internal Flarum container
  binding.** `POST /p/{slug}` now resolves through Flarum's own
  `RouteHandlerFactory::toForum()` — the documented factory `Extend\Frontend` uses
  for the GET route — via a small `PageFormController`, instead of reaching for the
  `flarum.frontend.forum` binding by name. Behaviour is identical (verified: PHP
  pages still receive `$_POST` / `$_FILES` and the CSRF token); the route is now
  resilient to core refactors of that binding and is unit-testable.
- **highlight.js language registration is shared.** The forum `PageView` and the
  admin editor preview now import one `common/hljs` module instead of each
  registering the same nine languages, so the set can never drift between them.
- **The PHP page sandbox resolves its `$pages` tree helper through the container.**
  `PhpRenderer` now builds `PageTree` via `container->make(…, ['actor' => …])`
  rather than `new`, so any future `PageTree` dependencies are auto-wired and it can
  be substituted in tests.
- Documented in the `parent_id` migration why it must use raw closures rather than
  the `Migration::addColumns()` helper (self-referencing foreign key).

*This release rebuilds the JS bundle, changes CSS and adds migrations — run
`php flarum migrate && php flarum cache:clear` after updating (`composer` installs
the prebuilt `js/dist`, so no Node build is needed on the server). Backward
compatible; nothing is pinned and no page redirects until you opt a page in.*

## [2.2.0] - 2026-06-22

### Added
- **“Select / Copy” buttons on code blocks.** Every code block on an Advanced
  Page now gets a small toolbar in its top-right corner: *Select* highlights the
  whole block, *Copy* copies the current selection — or the entire block when
  nothing is selected — to the clipboard (with a brief “Copied!” confirmation). A
  new admin setting, **“Show Select / Copy buttons on code blocks in forum posts”**
  (Forum Integration, off by default), extends the same toolbar to the code blocks
  of regular forum posts.

### Changed
- **Code blocks are now height-capped and scroll internally.** A tall code block
  on a page is limited to `max(50vh, 250px)` and scrolls inside, instead of
  stretching the page to its full height — mirroring how forum posts render code.
- **The page editor modal keeps its header pinned while the body scrolls.** The
  modal's flex chain targeted the wrong level — Flarum's `FormModal` nests the
  content as `.Modal-content > form > .Modal-header + .Modal-body`, so on tall
  content the header scrolled away with the form. The form is now the flex column
  and the body scrolls under a fixed header.
- **Hardened the editor modal's desktop height against stricter LESS compilers.**
  The `max-height: calc(100vh - 60px)` rules are now wrapped in LESS
  string-escaping (`~"…"`). Some `less.php` builds pre-evaluate mixed-unit
  `calc()` (`vh` combined with `px`) down to a bogus `calc(40vh)`, which renders
  the modal far too short; the escape forces the expression through untouched.

### Fixed
- **Code blocks on preserve-newline BBCode pages no longer collapse onto a single
  line.** In `preserve` newline mode the renderer turns line breaks into `<br>`,
  including inside `[code]` blocks; the pass that converts those back to real
  newlines required `</code></pre>` to be adjacent, but s9e TextFormatter inserts
  its highlight.js loader `<script>` tags between `</code>` and `</pre>`, so it
  never matched. The surviving `<br>`s were then flattened by the frontend's
  highlight.js (which reads `textContent`, where a `<br>` contributes nothing),
  dropping the whole block onto one line. The repair now matches up to `</code>`
  regardless of trailing scripts, so code renders multi-line exactly as it does in
  forum posts. Flarum-mode pages were never affected.
- **Oversized empty gap above page content, most visible under breadcrumbs.** A
  leading heading applied its `margin-top` (1.5em) on top of the content's own top
  padding, and breadcrumbs stacked their bottom margin on as well — together a
  ~110px empty band under the hero. The first child's top margin is now zeroed and
  the content's top padding is trimmed when breadcrumbs precede it. Measured
  hero→first-line gap dropped from 112px to 45px with breadcrumbs and 75px to 30px
  without; spacing between subsequent elements is unchanged.

*This release rebuilds the JS bundle and changes CSS — run `php flarum cache:clear`
after updating (`composer` installs the prebuilt `js/dist`, so no Node build is
needed on the server). No new migrations; fully backward compatible.*

## [2.1.5] - 2026-06-21

### Changed
- **Ancestor chain now resolves in a fixed two queries regardless of nesting
  depth.** Building on 2.1.3's per-request memoisation, the parent walk no longer
  lazy-loads one `SELECT` per level: it reads the whole `(id → parent_id)` forest
  in a single lightweight query, walks it in memory, then hydrates the ancestor
  rows in one more query. A page N levels deep now issues 2 queries instead of N.
  Pure query-builder, so it stays cross-database (MySQL/MariaDB, SQLite,
  PostgreSQL); breadcrumb output is byte-identical.
- **Parent-cycle check is skipped when the parent isn't changing, and otherwise
  runs in a single query.** Editing a page without touching its `parentId` (e.g. a
  content-only edit) no longer walks the ancestry at all; when the parent *does*
  change, the check reads one prefetched `(id → parent_id)` map instead of one
  `Page::find()` per level. The 422-on-cycle behaviour is unchanged.
- **Formatter configuration now uses constructor injection.** The custom BBCode
  setup (`[table]`, `[spoiler]`, `[center]`) moved from a `resolve()` call inside a
  `configure()` closure to an injected, invokable `FormatterConfigurator` class.
  Internal cleanup only — the compiled formatter output is byte-identical, verified
  across this extension's tags and unaffected third-party tags.
- The *Allow script execution* checkbox **label** now reads “Allow script tags to
  run on this page”, matching the help text that already (since 2.1.1) spells out
  that the toggle gates `<script>` tags only and is **not** a sandbox.
- Internal tidy-ups: the PHP renderer balances its error handler with
  `restore_error_handler()` instead of re-setting the previous one, and
  content-type validation references the `CONTENT_TYPES` constant instead of a
  duplicated literal list.

### Fixed
- **The extended `[url]` parser no longer rewrites `[url]` inside code blocks.**
  With the optional `bbcode_url` setting enabled, a literal `[url]…[/url]` written
  inside a `[code]` block (or any `<pre>`/`<code>`) was turned into a link; those
  regions are now shielded before the post-render pass, so code samples render
  verbatim. The setting is off by default, so only forums that enabled it were
  affected.

*No new migrations and no JS/asset changes (`js/dist/*` unchanged); fully backward
compatible. Run `php flarum cache:clear` after updating — the formatter
configuration and a UI label changed.*

## [2.1.4] - 2026-06-12

### Changed
- `PageResource` now resolves the `PageRenderer` once when the resource boots —
  via the framework's `boot()`/container, the same way it injects events and
  validation (the `Bootable` concern) — instead of calling the global `resolve()`
  helper inside the `contentHtml` getter on every single-page request. Purely an
  internal dependency-resolution cleanup; no behaviour change, no migrations, no
  JS changes.

## [2.1.3] - 2026-06-12

### Changed
- **Ancestor chain is now walked once per request and memoised.** A nested
  page's breadcrumbs read both its ancestor list (`ancestors()`) and its tree
  root (`rootPage()`); these previously triggered two independent parent-chain
  walks, each lazy-loading every level — an extra query per level, doubled. The
  chain is now traversed a single time and cached on the model, so a page N
  levels deep no longer issues ~2N parent queries on every single-page view.
  Behaviour is unchanged: the full chain is still followed regardless of
  per-page visibility, so breadcrumbs render identically.

### Fixed
- **Parent-cycle rejection now returns a 422 validation error, not a 403.**
  Assigning a parent that would make a page its own ancestor is invalid input,
  not a permission failure. It now raises a `ValidationException` pointing at the
  `parentId` field (`/data/attributes/parentId`) instead of a
  `PermissionDeniedException`, so API clients and the admin UI receive a correct,
  field-level error message.
- README *Requirements* now states PHP `^8.2`, matching `composer.json` (it was
  an outdated `^8.1`).

## [2.1.2] - 2026-06-11

### Changed
- `composer.json` metadata: expanded the description and keywords (nested pages,
  breadcrumbs, permissions), raised the PHP floor to `^8.2`, pinned
  `flarum/core` to `^2.0.0-rc.1`, and normalised the extension icon colour. No
  runtime behaviour change.

### Chore
- Removed `.editorconfig` and tidied `.gitignore`.

## [2.1.1] - 2026-06-10

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
