<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

class HtmlRenderer implements RendererInterface
{
    public function supports(string $contentType): bool
    {
        return $contentType === 'html';
    }

    public function render(Page $page, ?User $actor = null): string
    {
        return '<div class="AdvancedPages-htmlContent">' . $page->content . '</div>';
    }
}
