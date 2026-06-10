<?php

use Flarum\Database\Migration;

return Migration::addColumns('advanced_pages', [
    'newline_mode' => ['string', 'length' => 20, 'default' => 'flarum'],
]);
