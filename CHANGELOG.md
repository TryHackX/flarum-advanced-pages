# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-04-09

### Added
- "Replace Forum Spoiler" setting - replaces Flarum's default inline spoiler with the Advanced Pages details/summary spoiler style across all forum posts
- "Forum Integration" settings section in admin panel

### Changed
- Moved support button to the top of the admin settings page
- Removed margin-top/padding-top/border-top CSS from the support button section

## [1.0.1] - Initial tracked release

### Added
- 5 content types: HTML, BBCode, Markdown, PHP, Plain Text
- Formatting toolbars for BBCode and Markdown editors
- Live preview with syntax highlighting
- CodeMirror-powered editor for HTML and PHP
- BBCode extensions: table, spoiler, center, extended URL parser
- Configurable newline modes for BBCode pages
- Spoiler system with permission-based visibility
- Admin panel with full CRUD, pagination, and inline settings
- SEO support with meta descriptions and title tags
- Access control: publish, hide, restrict, per-group visibility
- Custom permissions for page management and spoiler viewing
- Clean URLs at /p/{slug}
