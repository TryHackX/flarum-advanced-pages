<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Optional parent page, enabling a nested hierarchy (e.g. docs ->
 * docs/getting-started). A raw closure is used because the column carries a
 * self-referencing foreign key. Deleting a parent detaches its children
 * (set null) rather than cascading, so content is never lost by accident.
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('advanced_pages')->onDelete('set null');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('advanced_pages', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    },
];
