<?php

namespace TryHackX\AdvancedPages\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

class PagePolicy extends AbstractPolicy
{
    public function view(User $actor, Page $page): string|bool|null
    {
        if ($actor->isAdmin()) {
            return $this->allow();
        }

        if (! $page->is_published) {
            return $this->deny();
        }

        if ($page->is_hidden) {
            return $this->deny();
        }

        if ($page->is_restricted && $actor->isGuest()) {
            return $this->deny();
        }

        return null;
    }

    public function edit(User $actor, Page $page): string|bool|null
    {
        if ($actor->hasPermission('advancedPages.manage')) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function delete(User $actor, Page $page): string|bool|null
    {
        if ($actor->hasPermission('advancedPages.manage')) {
            return $this->allow();
        }

        return $this->deny();
    }
}
