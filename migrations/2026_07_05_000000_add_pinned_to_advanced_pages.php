<?php

use Flarum\Database\Migration;

/*
 * `is_pinned` opts a page into the forum's index sidebar navigation (the same
 * "Dropdown-menu" list where the Members/Badges links live), so admins can
 * surface a page as a permanent menu entry. `pinned_icon` is an optional
 * FontAwesome class (e.g. "fas fa-book") shown next to the link — nullable, so
 * a pinned page without one simply falls back to a default icon on the frontend.
 * `pinned_label` is an optional short menu label used instead of the (possibly
 * long) page title in the nav — nullable, falling back to the title when blank.
 */
return Migration::addColumns('advanced_pages', [
    'is_pinned' => ['boolean', 'default' => false],
    'pinned_icon' => ['string', 'length' => 100, 'nullable' => true],
    'pinned_label' => ['string', 'length' => 100, 'nullable' => true],
]);
