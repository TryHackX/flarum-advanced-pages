<?php

namespace TryHackX\AdvancedPages;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only helper for navigating the page tree from inside a PHP page (exposed
 * there as `$pages`). Everything is scoped to what the current viewer is allowed
 * to see (drafts, hidden, restricted and per-group pages are filtered out for
 * users who can't see them), and ordered by position then title — so a menu
 * built from it matches the admin tree and never leaks hidden pages.
 *
 * All visible pages are loaded once (lazily) and every method works off that
 * in-memory set, so it stays a single query no matter how many calls you make.
 */
class PageTree
{
    protected ?Collection $cache = null;

    public function __construct(
        protected ?User $actor = null
    ) {
    }

    /** Every page the viewer can see, flat, ordered by position then title. */
    public function all(): Collection
    {
        if ($this->cache === null) {
            $query = Page::query()->orderBy('position')->orderBy('title');

            if ($this->actor) {
                $query->whereVisibleTo($this->actor);
            }

            $this->cache = $query->get();
        }

        return $this->cache;
    }

    /** Top-level pages (no parent). */
    public function roots(): Collection
    {
        return $this->all()->filter(fn (Page $p) => $p->parent_id === null)->values();
    }

    /** Direct children of a page (accepts a Page, an id, or a slug). */
    public function children(Page|int|string|null $parent): Collection
    {
        $parentId = $this->idOf($parent);

        return $this->all()->filter(fn (Page $p) => (int) $p->parent_id === (int) $parentId && $p->parent_id !== null)->values();
    }

    /** Find a single page by slug (e.g. "pages/animals") or numeric id. */
    public function find(int|string $slugOrId): ?Page
    {
        return $this->all()->first(
            fn (Page $p) => $p->slug === (string) $slugOrId || (string) $p->id === (string) $slugOrId
        );
    }

    /**
     * The nested tree as an array of ['page' => Page, 'children' => [...]] nodes.
     * Pass null for the whole forest, or a Page/id/slug for just that subtree
     * (e.g. $pages->tree('pages') for everything under /p/pages).
     */
    public function tree(Page|int|string|null $root = null): array
    {
        return $this->build($this->idOf($root));
    }

    /** Ancestor chain of a page, root → immediate parent (excludes the page). */
    public function ancestors(Page|int|string $page): array
    {
        $page = $page instanceof Page ? $page : $this->find($page);

        $chain = [];
        $seen = [];

        while ($page && $page->parent_id !== null && ! isset($seen[$page->parent_id])) {
            $seen[$page->parent_id] = true;
            $parent = $this->all()->firstWhere('id', $page->parent_id);
            if (! $parent) {
                break;
            }
            array_unshift($chain, $parent);
            $page = $parent;
        }

        return $chain;
    }

    protected function build(?int $parentId): array
    {
        return $this->all()
            ->filter(fn (Page $p) => ($p->parent_id === null ? null : (int) $p->parent_id) === $parentId)
            ->map(fn (Page $p) => ['page' => $p, 'children' => $this->build((int) $p->id)])
            ->values()
            ->all();
    }

    protected function idOf(Page|int|string|null $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Page) {
            return (int) $value->id;
        }

        $found = $this->find($value);

        return $found ? (int) $found->id : null;
    }
}
