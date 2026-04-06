<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Log\LoggerInterface;
use TryHackX\AdvancedPages\Page;

class PhpRenderer implements RendererInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function supports(string $contentType): bool
    {
        return $contentType === 'php';
    }

    public function render(Page $page, ?User $actor = null): string
    {
        ob_start();

        $previousHandler = set_error_handler(function (int $severity, string $message, string $file, int $line) {
            $this->logger->warning('AdvancedPages PHP render error', [
                'page_id' => 'N/A',
                'severity' => $severity,
                'message' => $message,
            ]);

            return true;
        });

        try {
            $this->executeInSandbox($page);
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
            set_error_handler($previousHandler);
        }

        return '<div class="AdvancedPages-phpContent">' . $output . '</div>';
    }

    protected function executeInSandbox(Page $page): void
    {
        $__page = $page;
        $__settings = $this->settings;

        $__actor = null;
        try {
            $request = resolve('flarum.instance')->getRequest();
            if ($request) {
                $__actor = $request->getAttribute('actor');
            }
        } catch (\Throwable $e) {
        }

        (function () use ($__page, $__actor, $__settings) {
            $page = $__page;
            $actor = $__actor;
            $settings = $__settings;

            unset($__page, $__actor, $__settings);

            eval('?>' . $page->content);
        })();
    }

    protected function getErrorMessage(): string
    {
        return 'An error occurred while rendering this page. Please contact the administrator.';
    }
}
