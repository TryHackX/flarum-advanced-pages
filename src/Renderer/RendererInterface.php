<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

interface RendererInterface
{
    public function render(Page $page, ?User $actor = null): string;

    public function supports(string $contentType): bool;
}
