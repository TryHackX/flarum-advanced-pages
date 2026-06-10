<?php

namespace TryHackX\AdvancedPages\Access;

use Flarum\Group\Group;
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

        // Per-group visibility. Mirrors ScopePageVisibility so that a direct
        // can('view') check (not just the listing scope) also enforces groups.
        // null/empty visible_groups means "everyone".
        $visibleGroups = $page->visible_groups;
        if (is_array($visibleGroups) && count($visibleGroups) > 0) {
            $actorGroupIds = $actor->isGuest()
                ? [Group::GUEST_ID]
                : $actor->groups->pluck('id')->push(Group::MEMBER_ID)->all();

            $actorGroupIds = array_map('intval', $actorGroupIds);

            $inVisibleGroup = false;
            foreach ($visibleGroups as $groupId) {
                if (in_array((int) $groupId, $actorGroupIds, true)) {
                    $inVisibleGroup = true;
                    break;
                }
            }

            if (! $inVisibleGroup) {
                return $this->deny();
            }
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
