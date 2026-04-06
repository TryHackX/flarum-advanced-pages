<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Formatter\Formatter;
use TryHackX\AdvancedPages\Page;

class MarkdownRenderer implements RendererInterface
{
    public function __construct(
        protected Formatter $formatter
    ) {
    }

    public function supports(string $contentType): bool
    {
        return $contentType === 'markdown';
    }

    public function render(Page $page, ?\Flarum\User\User $actor = null): string
    {
        $xml = $this->formatter->parse($page->content, null, null);
        $html = $this->formatter->render($xml);

        return '<div class="AdvancedPages-markdownContent">' . $html . '</div>';
    }
}
