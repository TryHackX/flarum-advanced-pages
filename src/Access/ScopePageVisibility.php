<?php

namespace TryHackX\AdvancedPages\Access;

use Flarum\Group\Group;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class ScopePageVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        $query->where('is_published', true)
            ->where('is_hidden', false);

        if ($actor->isGuest()) {
            $query->where('is_restricted', false);
        }

        $query->where(function (Builder $query) use ($actor) {
            $query->whereNull('visible_groups');

            $groupIds = $actor->isGuest()
                ? [Group::GUEST_ID]
                : array_unique(array_merge(
                    $actor->groups->pluck('id')->toArray(),
                    [Group::MEMBER_ID]
                ));

            // whereJsonContains() resolves to the correct JSON operator for each
            // supported database driver (MySQL/MariaDB, SQLite 3.38+, PostgreSQL),
            // unlike the MySQL-only JSON_CONTAINS() function.
            foreach ($groupIds as $groupId) {
                $query->orWhereJsonContains('visible_groups', (int) $groupId);
            }
        });
    }
}
