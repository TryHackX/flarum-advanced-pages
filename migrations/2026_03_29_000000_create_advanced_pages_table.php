<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('advanced_pages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->longText('content');
            $table->string('content_type', 10)->default('html');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_restricted')->default(false);
            $table->text('meta_description')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('edit_user_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('edit_user_id')->references('id')->on('users')->onDelete('set null');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('advanced_pages');
    },
];
