<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Formatter\Formatter;
use Illuminate\Contracts\Cache\Repository as Cache;
use TryHackX\AdvancedPages\Page;

class MarkdownRenderer implements RendererInterface
{
    /** How long a rendered page stays cached (1 week). Edits bust it via the key. */
    protected const CACHE_TTL = 604800;

    public function __construct(
        protected Formatter $formatter,
        protected Cache $cache
    ) {
    }

    public function supports(string $contentType): bool
    {
        return $contentType === 'markdown';
    }

    public function render(Page $page, ?\Flarum\User\User $actor = null, ?string $csrfToken = null): string
    {
        $content = $page->content;

        // Markdown output is the same for every viewer, so cache the parse+render.
        // Keyed on the content hash → editing the page yields a new key; a
        // `php flarum cache:clear` (needed after formatter setting changes) wipes it.
        $html = $this->cache->remember(
            'tryhackx-advanced-pages.render.md.' . sha1($content),
            self::CACHE_TTL,
            fn () => $this->formatter->render($this->formatter->parse($content, null, null))
        );

        return '<div class="AdvancedPages-markdownContent">' . $html . '</div>';
    }
}
