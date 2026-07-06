<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\PageTree;

class PhpRenderer implements RendererInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected SettingsRepositoryInterface $settings,
        protected Container $container
    ) {
    }

    public function supports(string $contentType): bool
    {
        return $contentType === 'php';
    }

    public function render(Page $page, ?User $actor = null, ?string $csrfToken = null): string
    {
        ob_start();

        set_error_handler(function (int $severity, string $message) use ($page) {
            $this->logger->warning('AdvancedPages PHP render error', [
                'page_id' => $page->id,
                'severity' => $severity,
                'message' => $message,
            ]);

            return true;
        });

        try {
            $this->executeInSandbox($page, $actor, $csrfToken);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();

            $this->logger->warning('AdvancedPages PHP render exception', [
                'page_id' => $page->id,
                'exception' => $e->getMessage(),
            ]);

            $output = '<div class="AdvancedPages-renderError">'
                . htmlspecialchars($this->getErrorMessage(), ENT_QUOTES, 'UTF-8')
                . '</div>';
        } finally {
            restore_error_handler();
        }

        return '<div class="AdvancedPages-phpContent">' . $output . '</div>';
    }

    protected function executeInSandbox(Page $page, ?User $actor, ?string $csrfToken): void
    {
        $__page = $page;
        $__settings = $this->settings;
        $__actor = $actor;
        $__csrfToken = $csrfToken;
        // Resolve through the container (passing the per-render actor) instead of
        // newing it up, so any future PageTree dependencies are auto-wired and
        // tests can substitute a fake.
        $__pages = $this->container->make(PageTree::class, ['actor' => $actor]);

        (function () use ($__page, $__actor, $__settings, $__csrfToken, $__pages) {
            $page = $__page;
            $actor = $__actor;
            $settings = $__settings;
            // CSRF token for the current session. Include it in POST forms that
            // submit to this page: <input type="hidden" name="csrfToken" value="...">
            // PHP superglobals ($_GET, $_POST, $_FILES, $_SERVER) are available too.
            $csrfToken = $__csrfToken;
            // Read-only navigator over the (visibility-scoped) page tree — handy
            // for building nav menus. See PageTree: ->all(), ->roots(),
            // ->children($slug), ->tree($slug), ->find($slug), ->ancestors($page).
            $pages = $__pages;

            unset($__page, $__actor, $__settings, $__csrfToken, $__pages);

            eval('?>' . $page->content);
        })();
    }

    protected function getErrorMessage(): string
    {
        return 'An error occurred while rendering this page. Please contact the administrator.';
    }
}
