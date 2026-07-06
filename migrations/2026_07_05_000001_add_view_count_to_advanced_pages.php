<?php

use Flarum\Database\Migration;

/*
 * `view_count` is a simple hit counter incremented once per page view (in the
 * by-slug view controller). Unsigned integer, default 0. Shown in the admin
 * page list and exposed to PHP pages as `$page->view_count`.
 */
return Migration::addColumns('advanced_pages', [
    'view_count' => ['integer', 'unsigned' => true, 'default' => 0],
]);
