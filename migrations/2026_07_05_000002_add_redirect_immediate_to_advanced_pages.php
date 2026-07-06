<?php

use Flarum\Database\Migration;

/*
 * `redirect_immediate` controls how a "redirect" page behaves when visited:
 * when true (default) the visitor is forwarded straight to the target; when
 * false they land on a small page showing the destination link (no auto-forward).
 * Either way the visit is counted (both go through /p/{slug}). Ignored by every
 * other content type.
 */
return Migration::addColumns('advanced_pages', [
    'redirect_immediate' => ['boolean', 'default' => true],
]);
