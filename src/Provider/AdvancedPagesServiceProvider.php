<?php

namespace TryHackX\AdvancedPages\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use TryHackX\AdvancedPages\Renderer\BbcodeRenderer;
use TryHackX\AdvancedPages\Renderer\HtmlRenderer;
use TryHackX\AdvancedPages\Renderer\MarkdownRenderer;
use TryHackX\AdvancedPages\Renderer\PageRenderer;
use TryHackX\AdvancedPages\Renderer\PhpRenderer;
use TryHackX\AdvancedPages\Renderer\TextRenderer;

class AdvancedPagesServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TextRenderer::class);
        $this->container->singleton(HtmlRenderer::class);
        $this->container->singleton(PhpRenderer::class);
        $this->container->singleton(BbcodeRenderer::class);
        $this->container->singleton(MarkdownRenderer::class);
        $this->container->singleton(PageRenderer::class);
    }
}
