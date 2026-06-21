<?php

namespace TryHackX\AdvancedPages;

use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string $content_type
 * @property bool $is_published
 * @property bool $is_hidden
 * @property bool $is_restricted
 * @property bool $allow_scripts
 * @property string|null $meta_description
 * @property string $newline_mode
 * @property array|null $visible_groups
 * @property int|null $parent_id
 * @property int $position
 * @property string|null $breadcrumbs_css
 * @property int|null $user_id
 * @property int|null $edit_user_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Page extends AbstractModel
{
    use ScopeVisibilityTrait;
    protected $table = 'advanced_pages';

    public $timestamps = true;

    protected $casts = [
        'is_published' => 'boolean',
        'is_hidden' => 'boolean',
        'is_restricted' => 'boolean',
        'allow_scripts' => 'boolean',
        'visible_groups' => 'array',
        'position' => 'integer',
    ];

    /** Memoised root→parent chain shared by ancestors() and rootPage(). */
    protected ?array $ancestorChainCache = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    /**
     * Pages from the tree root down to this page's immediate parent (excludes
     * self), in breadcrumb order. Cycle-safe.
     *
     * @return Page[]
     */
    public function ancestors(): array
    {
        return $this->ancestorChain();
    }

    /**
     * The top-most page of this page's tree (this page itself if it is a root).
     * Cycle-safe.
     */
    public function rootPage(): Page
    {
        $chain = $this->ancestorChain();

        return $chain[0] ?? $this;
    }

    /**
     * Resolve the parent chain once (root → immediate parent, excludes self) and
     * memoise it, so a single request that reads both ancestors() and rootPage()
     * shares one resolution.
     *
     * Instead of lazily following $parent->parent one SELECT per level (an N+1
     * that grows with nesting depth), this reads the whole (id → parent_id)
     * forest in a single lightweight query and walks it in memory to find the
     * ordered ancestor ids, then hydrates just those rows in one more query —
     * two queries total regardless of depth. Pure query-builder, so it stays
     * cross-database (MySQL/MariaDB, SQLite, PostgreSQL).
     *
     * Cycle-safe: stops as soon as an id has already been seen.
     *
     * @return Page[]
     */
    protected function ancestorChain(): array
    {
        if ($this->ancestorChainCache !== null) {
            return $this->ancestorChainCache;
        }

        // Root page (no parent): empty chain, no queries.
        if ($this->parent_id === null) {
            return $this->ancestorChainCache = [];
        }

        // One query for the whole forest as id => parent_id.
        $parentOf = static::query()->pluck('parent_id', 'id')->all();

        // Walk parents in memory, root-first, stopping on any repeat (cycle-safe).
        $ids = [];
        $seen = [(int) $this->id => true];
        $cursor = (int) $this->parent_id;

        while (! isset($seen[$cursor])) {
            $seen[$cursor] = true;
            array_unshift($ids, $cursor);

            $next = $parentOf[$cursor] ?? null;
            if ($next === null) {
                break;
            }
            $cursor = (int) $next;
        }

        if (! $ids) {
            return $this->ancestorChainCache = [];
        }

        // One query to hydrate the ancestor rows, then restore root → parent order.
        $byId = static::query()->findMany($ids)->keyBy('id');

        $chain = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $chain[] = $byId[$id];
            }
        }

        return $this->ancestorChainCache = $chain;
    }
}
