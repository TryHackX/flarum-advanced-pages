<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\RedirectTarget;

/**
 * A "redirect" page holds a destination URL in its content and forwards visitors
 * there. Immediate pages are forwarded up-front (a 302 from PageViewController, or
 * a 0s meta-refresh / client redirect as fallback). Landing pages (immediate off)
 * show this body — a big "You will be redirected in N s…" countdown above the
 * destination link — and forward automatically once it reaches zero.
 *
 * The target is validated (http(s) or root-relative only) and HTML-escaped, so it
 * cannot inject markup or a dangerous scheme.
 */
class RedirectRenderer implements RendererInterface
{
    /** How long a landing page waits before forwarding (seconds). */
    public const COUNTDOWN_SECONDS = 5;

    public function __construct(
        protected TranslatorInterface $translator
    ) {
    }

    public function supports(string $contentType): bool
    {
        return $contentType === 'redirect';
    }

    public function render(Page $page, ?User $actor = null, ?string $csrfToken = null): string
    {
        $target = RedirectTarget::sanitize($page->content);

        if ($target === null) {
            return '<div class="AdvancedPages-redirect AdvancedPages-redirect--invalid">'
                . '<p>' . htmlspecialchars($this->translator->trans('tryhackx-advanced-pages.forum.redirect.invalid'), ENT_QUOTES, 'UTF-8')
                . '</p></div>';
        }

        $escaped = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
        $link = '<a class="AdvancedPages-redirectLink" href="' . $escaped . '" rel="noopener noreferrer">' . $escaped . '</a>';

        // Immediate pages forward before this body is ever really seen (a 302, or an
        // instant client redirect), so just the link as a bare fallback.
        if ($page->redirect_immediate) {
            return '<div class="AdvancedPages-redirect">' . $link . '</div>';
        }

        // Landing page: a big "You will be redirected in N s…" countdown on top,
        // then the destination link below. The seconds value is a separate <span>
        // the frontend ticks down; a sentinel is swapped in *after* escaping so the
        // number's markup isn't escaped, while the translated text still is.
        $countdown = str_replace(
            '__SECONDS__',
            '<span class="AdvancedPages-redirectSeconds">' . self::COUNTDOWN_SECONDS . '</span>',
            htmlspecialchars(
                $this->translator->trans('tryhackx-advanced-pages.forum.redirect.countdown', ['seconds' => '__SECONDS__']),
                ENT_QUOTES,
                'UTF-8'
            )
        );

        return '<div class="AdvancedPages-redirect" data-redirect-seconds="' . self::COUNTDOWN_SECONDS . '">'
            . '<p class="AdvancedPages-redirectCountdown">' . $countdown . '</p>'
            . $link
            . '</div>';
    }
}
