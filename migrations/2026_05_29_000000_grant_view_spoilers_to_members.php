<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

/*
 * Default the "View spoiler content" permission to registered members.
 *
 * Without an explicit grant the permission is effectively admin-only (admins
 * bypass all checks), which is not the intended default. addPermissions() only
 * inserts the grant when it does not already exist, so this is safe to run on
 * existing installs and never overrides a value an admin has changed.
 */
return Migration::addPermissions([
    'advancedPages.viewSpoilers' => Group::MEMBER_ID,
]);
