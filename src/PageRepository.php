<?php

namespace TryHackX\AdvancedPages;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class PageRepository
{
    public function query(): Builder
    {
        return Page::query();
    }

    public function findOrFail(int $id, User $actor = null): Page
    {
        $query = $this->query();

        if ($actor !== null) {
            $query->whereVisibleTo($actor);
        }

        return $query->findOrFail($id);
    }

    public function findBySlug(string $slug, User $actor = null): Page
    {
        $query = $this->query()->where('slug', $slug);

        if ($actor !== null) {
            $query->whereVisibleTo($actor);
        }

        return $query->firstOrFail();
    }
}
