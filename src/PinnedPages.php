<?php

namespace TryHackX\AdvancedPages;

use Flarum\User\User;

/**
 * Resolves the pages an admin has pinned to the forum's index-sidebar navigation,
 * scoped to what the current viewer may see and ordered exactly like the admin
 * page list (depth-first through the parent/position tree).
 *
 * This runs on every forum page load (it feeds a ForumResource attribute), so it
 * is deliberately cheap: a SINGLE query that selects only the small structural
 * columns — never the potentially large `content` blob — from the (inherently
 * small, admin-managed) pages table.
 */
class PinnedPages
{
    /** Columns needed to order the tree and render the nav links — never `content`. */
    protected const COLUMNS = ['id', 'parent_id', 'position', 'title', 'slug', 'is_pinned', 'pinned_icon', 'pinned_label'];

    public function __construct(
        protected ?User $actor = null
    ) {
    }

    /**
     * Pinned pages the viewer can see, in page-list order. Every link points at
     * the page's own /p/{slug} route (never straight at a redirect's destination),
     * so a click always passes through the counted view path — a redirect page
     * then forwards from there.
     *
     * @return array<int, array{title: string, slug: string, icon: string|null}>
     */
    public function forNav(): array
    {
        // Fast path for the overwhelmingly common case (nothing pinned): a single
        // boolean EXISTS that hydrates no models and skips the tree flatten below.
        // Runs on every forum page load, so keeping the empty case this cheap
        // matters on large forums.
        if (! Page::query()->where('is_pinned', true)->exists()) {
            return [];
        }

        $query = Page::query()
            ->orderBy('position')
            ->orderBy('title');

        if ($this->actor) {
            $query->whereVisibleTo($this->actor);
        }

        $pages = $query->get(self::COLUMNS);

        if ($pages->isEmpty()) {
            return [];
        }

        // Group children by parent id (roots keyed under 0), preserving the
        // position/title order already applied by the query.
        $childrenOf = [];
        foreach ($pages as $page) {
            $childrenOf[(int) ($page->parent_id ?? 0)][] = $page;
        }

        // Depth-first flatten (roots first, each page's children right after it),
        // cycle-safe, mirroring the admin AdvancedPagesPage tree order.
        $ordered = [];
        $seen = [];

        $walk = function (int $parentId) use (&$walk, &$ordered, &$seen, $childrenOf): void {
            foreach ($childrenOf[$parentId] ?? [] as $page) {
                $id = (int) $page->id;
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $ordered[] = $page;
                $walk($id);
            }
        };
        $walk(0);

        // Any page whose parent is not visible to this viewer never gets reached
        // above; append such orphans in their original order so they are not lost.
        foreach ($pages as $page) {
            if (! isset($seen[(int) $page->id])) {
                $ordered[] = $page;
            }
        }

        $nav = [];
        foreach ($ordered as $page) {
            if (! $page->is_pinned) {
                continue;
            }

            $nav[] = [
                // Effective menu label: the short name if set, else the page title.
                'title' => ($page->pinned_label !== null && $page->pinned_label !== '')
                    ? $page->pinned_label
                    : $page->title,
                'slug' => $page->slug,
                'icon' => $page->pinned_icon ?: null,
            ];
        }

        return $nav;
    }
}
