<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->text('visible_groups')->nullable()->after('is_restricted');
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->dropColumn('visible_groups');
        });
    },
];
