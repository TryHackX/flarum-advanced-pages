# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
