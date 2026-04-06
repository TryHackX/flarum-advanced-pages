<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

class PageRenderer
{
    /** @var RendererInterface[] */
    protected array $renderers;

    public function __construct(
        TextRenderer $text,
        HtmlRenderer $html,
        PhpRenderer $php,
        BbcodeRenderer $bbcode,
        MarkdownRenderer $markdown
    ) {
        $this->renderers = [$text, $html, $php, $bbcode, $markdown];
    }

    public function render(Page $page, ?User $actor = null): string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($page->content_type)) {
                return $renderer->render($page, $actor);
            }
        }

        return '<div class="AdvancedPages-textContent">'
            . htmlspecialchars($page->content, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }
}
