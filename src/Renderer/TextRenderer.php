<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

class TextRenderer implements RendererInterface
{
    public function supports(string $contentType): bool
    {
        return $contentType === 'text';
    }

    public function render(Page $page, ?User $actor = null): string
    {
        $escaped = htmlspecialchars($page->content, ENT_QUOTES, 'UTF-8');
        $escaped = nl2br($escaped);

        $escaped = preg_replace(
            '/(https?:\/\/[^\s<&]+)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $escaped
        );

        return '<div class="AdvancedPages-textContent">' . $escaped . '</div>';
    }
}
