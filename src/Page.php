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
        $chain = [];
        $seen = [$this->id => true];
        $parent = $this->parent;

        while ($parent && ! isset($seen[$parent->id])) {
            $seen[$parent->id] = true;
            array_unshift($chain, $parent);
            $parent = $parent->parent;
        }

        return $chain;
    }

    /**
     * The top-most page of this page's tree (this page itself if it is a root).
     * Cycle-safe.
     */
    public function rootPage(): Page
    {
        $node = $this;
        $seen = [$this->id => true];

        while ($node->parent && ! isset($seen[$node->parent->id])) {
            $seen[$node->parent->id] = true;
            $node = $node->parent;
        }

        return $node;
    }
}
