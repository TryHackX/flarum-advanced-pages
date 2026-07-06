<?php

namespace TryHackX\AdvancedPages\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Formatter\Formatter;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use TryHackX\AdvancedPages\Renderer\BbcodeRenderer;
use TryHackX\AdvancedPages\Renderer\HtmlRenderer;
use TryHackX\AdvancedPages\Renderer\MarkdownRenderer;
use TryHackX\AdvancedPages\Renderer\PageRenderer;
use TryHackX\AdvancedPages\Renderer\PhpRenderer;
use TryHackX\AdvancedPages\Renderer\RedirectRenderer;
use TryHackX\AdvancedPages\Renderer\TextRenderer;

class AdvancedPagesServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TextRenderer::class);
        $this->container->singleton(HtmlRenderer::class);
        $this->container->singleton(PhpRenderer::class);
        $this->container->singleton(RedirectRenderer::class);

        // The BBCode and Markdown renderers cache the (expensive) s9e parse+render
        // step. They share Flarum's own file cache store ('cache.filestore'), so a
        // `php flarum cache:clear` — already required after changing BBCode/formatter
        // settings — clears these entries too. Cache keys are derived from the page
        // content, so editing a page naturally produces a fresh key.
        $this->container->singleton(BbcodeRenderer::class, function (Container $container) {
            return new BbcodeRenderer(
                $container->make(Formatter::class),
                $container->make(SettingsRepositoryInterface::class),
                $container->make(TranslatorInterface::class),
                new Repository($container->make('cache.filestore'))
            );
        });

        $this->container->singleton(MarkdownRenderer::class, function (Container $container) {
            return new MarkdownRenderer(
                $container->make(Formatter::class),
                new Repository($container->make('cache.filestore'))
            );
        });

        $this->container->singleton(PageRenderer::class);
    }
}
