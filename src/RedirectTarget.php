<?php

namespace TryHackX\AdvancedPages;

/**
 * Validates the destination of a "redirect" page. A redirect's target becomes an
 * HTTP redirect / a bare `href`, so it must never carry a dangerous scheme
 * (`javascript:`, `data:`, …). Only two shapes are accepted:
 *
 *   - an absolute http(s) URL: `https://example.com/…`
 *   - a root-relative path:    `/tags`, `/d/5` (but NOT protocol-relative `//host`)
 *
 * Everything else is rejected on save and, defensively, wherever the target is
 * rendered.
 */
final class RedirectTarget
{
    public static function isSafe(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Reject any whitespace or control character (a valid URL contains none —
        // spaces must be %20-encoded). This notably blocks CR/LF, so the target can
        // never inject a response header when used as a 302 `Location`, nor break
        // out of the meta-refresh attribute.
        if (preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }

        // Root-relative path, but not the protocol-relative "//host" form.
        if ($url[0] === '/' && ! str_starts_with($url, '//')) {
            return true;
        }

        // Absolute http/https URL.
        return (bool) preg_match('#^https?://#i', $url);
    }

    /** The trimmed target if it is safe, otherwise null. */
    public static function sanitize(?string $url): ?string
    {
        $url = trim((string) $url);

        return self::isSafe($url) ? $url : null;
    }
}
