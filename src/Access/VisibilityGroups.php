<?php

namespace TryHackX\AdvancedPages\Access;

use Flarum\Group\Group;
use Flarum\User\User;

/**
 * The group ids that decide whether a viewer sees a group-restricted page.
 * Shared by PagePolicy (direct can('view') checks) and ScopePageVisibility
 * (listings), so the two can never drift apart.
 *
 * Logged-in users are judged by their PERMISSION groups: core's group processors
 * apply, so a suspended user (flarum/suspend) or an account whose e-mail is not
 * confirmed yet counts as a guest here — exactly as for every other permission
 * in Flarum. "Guest" in a page's groups keeps meaning "not logged in" for
 * everyone else: permissionGroupIds() always contains the guest group, so it is
 * dropped as soon as the member group is present.
 */
final class VisibilityGroups
{
    /**
     * @return list<int>
     */
    public static function of(User $actor): array
    {
        if ($actor->isGuest()) {
            return [Group::GUEST_ID];
        }

        $ids = array_map('intval', $actor->permissionGroupIds());

        if (! in_array(Group::MEMBER_ID, $ids, true)) {
            return [Group::GUEST_ID];
        }

        return array_values(array_unique(array_diff($ids, [Group::GUEST_ID])));
    }
}
