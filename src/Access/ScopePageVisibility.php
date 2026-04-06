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

            foreach ($groupIds as $groupId) {
                $query->orWhereRaw('JSON_CONTAINS(visible_groups, ?)', [json_encode((int) $groupId)]);
            }
        });
    }
}
