<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Script execution inside page content is now opt-in per page (column
 * `allow_scripts`). A raw closure is used here — rather than the
 * Flarum\Database\Migration helper — because the up step needs a data
 * migration: existing HTML/PHP pages keep executing scripts so nothing breaks,
 * while every new page defaults to scripts disabled (secure by default).
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->boolean('allow_scripts')->default(false);
        });

        $schema->getConnection()
            ->table('advanced_pages')
            ->whereIn('content_type', ['html', 'php'])
            ->update(['allow_scripts' => true]);
    },
    'down' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->dropColumn('allow_scripts');
        });
    },
];
