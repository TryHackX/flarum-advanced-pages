<?php

use Flarum\Database\Migration;

/*
 * `position` orders pages within their parent (drag-and-drop ordering in the
 * admin). `breadcrumbs_css` holds optional per-tree breadcrumb CSS, set on a
 * root page and applied to its whole tree.
 */
return Migration::addColumns('advanced_pages', [
    'position' => ['integer', 'default' => 0],
    'breadcrumbs_css' => ['text', 'nullable' => true],
]);
