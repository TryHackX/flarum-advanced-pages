<?php

use Flarum\Database\Migration;

// Cross-database column ordering is cosmetic, so no ->after() is used here
// (it is silently ignored on SQLite/PostgreSQL anyway).
return Migration::addColumns('advanced_pages', [
    'visible_groups' => ['text', 'nullable' => true],
]);
