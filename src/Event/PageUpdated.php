<?php

namespace TryHackX\AdvancedPages\Event;

use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

class PageUpdated
{
    public function __construct(
        public Page $page,
        public User $actor
    ) {
    }
}
