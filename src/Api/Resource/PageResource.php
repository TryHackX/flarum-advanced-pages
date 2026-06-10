<?php

namespace TryHackX\AdvancedPages\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;
use TryHackX\AdvancedPages\Event;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\Renderer\PageRenderer;

/**
 * @extends AbstractDatabaseResource<Page>
 */
class PageResource extends AbstractDatabaseResource
{
    public const CONTENT_TYPES = ['text', 'bbcode', 'markdown', 'html', 'php'];

    /**
     * Slug for a page or a nested path: lowercase segments of [a-z0-9-] joined
     * by single slashes (e.g. "docs", "docs/getting-started"). No leading or
     * trailing slash and no doubled slashes.
     */
    public const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/i';

    /**
     * Content types whose pages can embed executable code (server-side PHP, or
     * client-side HTML/JS). These require their dedicated creation permission
     * even when editing an existing page, so a page manager cannot escalate a
     * page into executable content without being explicitly granted the right
     * (which, for html/php, is only grantable via the advancedpages:permission
     * console command — never the admin permission grid).
     */
    public const SENSITIVE_TYPES = ['html', 'php'];

    public function type(): string
    {
        return 'advanced-pages';
    }

    public function model(): string
    {
        return Page::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function find(string $id, Context $context): ?object
    {
        if (is_numeric($id)) {
            return $this->query($context)->find($id);
        }

        return $this->query($context)->where('slug', $id)->first();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('-createdAt')
                ->paginate(20, 50),
            Endpoint\Show::make(),
            // Creation is gated per content type in saving(): the actor must hold
            // advancedPages.create.<type> for the type being created. Admins bypass.
            Endpoint\Create::make()
                ->authenticated(),
            Endpoint\Update::make()
                ->authenticated()
                ->can('edit'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(200),

            Schema\Str::make('slug')
                ->requiredOnCreate()
                ->writable()
                ->unique('advanced_pages', 'slug', true)
                ->regex(self::SLUG_REGEX)
                ->maxLength(200),

            Schema\Str::make('content')
                ->requiredOnCreate()
                ->writable()
                ->visible(fn ($page, FlarumContext $context) => $context->getActor()->isAdmin()),

            Schema\Str::make('contentType')
                ->requiredOnCreate()
                ->writable()
                ->property('content_type')
                ->in(['html', 'php', 'text', 'bbcode', 'markdown']),

            Schema\Str::make('contentHtml')
                ->get(function (Page $page, FlarumContext $context) {
                    // Don't render in list contexts — it can be expensive (PHP pages
                    // run eval). Single-page fetches use the Show endpoint (resolved
                    // by id, including via the by-slug route) and do render this.
                    if ($context->listing()) {
                        return null;
                    }

                    // Expose the session CSRF token to PHP pages so their forms can
                    // POST back to /p/{slug} (the POST route enforces CSRF).
                    $session = $context->request->getAttribute('session');
                    $csrfToken = $session ? $session->token() : null;

                    return resolve(PageRenderer::class)->render($page, $context->getActor(), $csrfToken);
                }),

            Schema\Str::make('newlineMode')
                ->writable()
                ->property('newline_mode')
                ->in(['flarum', 'preserve']),

            Schema\Boolean::make('isPublished')
                ->writable()
                ->property('is_published'),

            Schema\Boolean::make('isHidden')
                ->writable()
                ->property('is_hidden'),

            Schema\Boolean::make('isRestricted')
                ->writable()
                ->property('is_restricted'),

            // Opt-in execution of <script> tags embedded in page content. Only
            // meaningful for html/php pages (other types escape their output);
            // creating those types already requires the sensitive permission.
            Schema\Boolean::make('allowScripts')
                ->writable()
                ->property('allow_scripts'),

            Schema\Str::make('metaDescription')
                ->writable()
                ->nullable()
                ->maxLength(500)
                ->property('meta_description'),

            Schema\Arr::make('visibleGroups')
                ->writable()
                ->nullable()
                ->property('visible_groups'),

            // Optional parent for nested pages (drives the admin tree, ordering
            // and breadcrumbs). The slug itself remains the independent URL.
            Schema\Integer::make('parentId')
                ->writable()
                ->nullable()
                ->property('parent_id'),

            // Order among siblings (drag-and-drop ordering in the admin).
            Schema\Integer::make('position')
                ->writable()
                ->property('position'),

            // Optional per-tree breadcrumb CSS, set on a root page (parent = none)
            // and applied to its whole tree. Plain attribute = the page's own value
            // (what the editor edits).
            Schema\Str::make('breadcrumbsCss')
                ->writable()
                ->nullable()
                ->property('breadcrumbs_css'),

            // Resolved breadcrumb CSS for THIS page's tree (the root's value),
            // injected by the frontend. Single-page fetches only.
            Schema\Str::make('treeBreadcrumbsCss')
                ->get(function (Page $page, FlarumContext $context) {
                    if ($context->listing()) {
                        return null;
                    }

                    return $page->rootPage()->breadcrumbs_css;
                }),

            // Ancestor chain (root → immediate parent) for breadcrumbs. Walks
            // parents, so only computed for single-page fetches, not lists.
            Schema\Arr::make('ancestors')
                ->get(function (Page $page, FlarumContext $context) {
                    if ($context->listing()) {
                        return null;
                    }

                    return array_map(
                        fn (Page $ancestor) => ['title' => $ancestor->title, 'slug' => $ancestor->slug],
                        $page->ancestors()
                    );
                }),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),

            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('editUser')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
            SortColumn::make('title'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        $model->user_id = $context->getActor()->id;

        return $model;
    }

    public function saving(object $model, Context $context): ?object
    {
        $actor = $context->getActor();
        $creating = $context->creating(self::class);

        if (! $creating) {
            $model->edit_user_id = $actor->id;
        }

        $type = $model->content_type ?: 'text';

        // Per-content-type permission enforcement. Admins bypass (hasPermission()
        // is always true for administrators). On create, every type requires its
        // grant; on update, the sensitive (code/script-capable) types require it
        // too, blocking escalation of an existing page into executable content.
        if ($creating || in_array($type, self::SENSITIVE_TYPES, true)) {
            if (! $actor->hasPermission('advancedPages.create.' . $type)) {
                throw new PermissionDeniedException();
            }
        }

        $this->assertNoParentCycle($model);

        return $model;
    }

    /**
     * Reject a parent assignment that would create a cycle (a page being its own
     * ancestor), which would otherwise make the hierarchy unrenderable.
     */
    protected function assertNoParentCycle(Page $model): void
    {
        if ($model->parent_id === null) {
            return;
        }

        $selfId = (int) $model->id;
        $ancestorId = (int) $model->parent_id;
        $seen = [];

        while ($ancestorId) {
            if ($selfId && $ancestorId === $selfId) {
                throw new PermissionDeniedException('A page cannot be its own parent or descendant.');
            }

            if (isset($seen[$ancestorId])) {
                break; // pre-existing cycle in stored data — don't loop forever
            }
            $seen[$ancestorId] = true;

            $parent = Page::find($ancestorId);
            if (! $parent) {
                break;
            }
            $ancestorId = (int) $parent->parent_id;
        }
    }

    public function created(object $model, Context $context): ?object
    {
        $this->events->dispatch(new Event\PageCreated($model, $context->getActor()));

        return $model;
    }

    public function saved(object $model, Context $context): ?object
    {
        if (! $context->creating(self::class)) {
            $this->events->dispatch(new Event\PageUpdated($model, $context->getActor()));
        }

        return $model;
    }

    public function deleting(object $model, Context $context): void
    {
        $this->events->dispatch(new Event\PageDeleted($model, $context->getActor()));
    }
}
