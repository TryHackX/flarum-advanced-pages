<?php

namespace TryHackX\AdvancedPages;

use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string $content_type
 * @property bool $is_published
 * @property bool $is_hidden
 * @property bool $is_restricted
 * @property string|null $meta_description
 * @property string $newline_mode
 * @property array|null $visible_groups
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
        'visible_groups' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_user_id');
    }
}
