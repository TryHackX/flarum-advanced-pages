<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->string('newline_mode', 20)->default('flarum')->after('content_type');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->dropColumn('newline_mode');
        });
    },
];
