<?php

namespace TryHackX\AdvancedPages\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\JsonApi;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;
use TryHackX\AdvancedPages\Event;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\RedirectTarget;
use TryHackX\AdvancedPages\Renderer\PageRenderer;

/**
 * @extends AbstractDatabaseResource<Page>
 */
class PageResource extends AbstractDatabaseResource
{
    public const CONTENT_TYPES = ['text', 'bbcode', 'markdown', 'html', 'php', 'redirect'];

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

    protected PageRenderer $pageRenderer;

    /**
     * Resolve the page renderer once when the resource boots — the same way the
     * framework injects events/validation (see the Bootable concern) — rather
     * than calling the global resolve() helper inside the contentHtml getter on
     * every single-page request.
     */
    public function boot(JsonApi $api): static
    {
        parent::boot($api);
        $this->pageRenderer = $api->getContainer()->make(PageRenderer::class);

        return $this;
    }

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
            // Treat a numeric value as an id first, but the slug regex also allows
            // all-digit slugs (e.g. "2024"), so fall back to a slug lookup when no
            // page has that id — otherwise such pages are unaddressable by the Show
            // endpoint.
            return $this->query($context)->find($id)
                ?? $this->query($context)->where('slug', $id)->first();
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

            // Raw source is exposed only to those who may edit pages: admins, or
            // holders of advancedPages.manage (page managers). Everyone else gets
            // only the rendered contentHtml. Managers need the raw content so an
            // edit (which resubmits every writable field) doesn't blank it.
            Schema\Str::make('content')
                ->requiredOnCreate()
                ->writable()
                ->visible(fn ($page, FlarumContext $context) =>
                    $context->getActor()->isAdmin()
                    || $context->getActor()->hasPermission('advancedPages.manage')),

            Schema\Str::make('contentType')
                ->requiredOnCreate()
                ->writable()
                ->property('content_type')
                ->in(self::CONTENT_TYPES),

            Schema\Str::make('contentHtml')
                ->get(function (Page $page, FlarumContext $context) {
                    // Don't render in list contexts — it can be expensive (PHP pages
                    // run eval). Single-page fetches use the Show endpoint (resolved
                    // by id, including via the by-slug route) and do render this.
                    if ($context->listing()) {
                        return null;
                    }

                    // Only render on reads (GET). A create/update (POST/PATCH) has no
                    // need for the rendered HTML — the editor closes on save — and
                    // rendering it would needlessly execute the page (a PHP page that
                    // calls exit()/header() would otherwise abort the save response).
                    if ($context->request->getMethod() !== 'GET') {
                        return null;
                    }

                    // Expose the session CSRF token to PHP pages so their forms can
                    // POST back to /p/{slug} (the POST route enforces CSRF).
                    $session = $context->request->getAttribute('session');
                    $csrfToken = $session ? $session->token() : null;

                    return $this->pageRenderer->render($page, $context->getActor(), $csrfToken);
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

            // Pin this page into the forum's index-sidebar navigation menu. The
            // pinned links are serialized to the forum payload (see the
            // ForumResource extender in extend.php), ordered like the page list.
            Schema\Boolean::make('isPinned')
                ->writable()
                ->property('is_pinned'),

            // Optional FontAwesome icon class (e.g. "fas fa-book") for the pinned
            // nav link. Rendered as an <i> class name by Mithril (escaped), so it
            // cannot inject markup.
            Schema\Str::make('pinnedIcon')
                ->writable()
                ->nullable()
                ->maxLength(100)
                ->property('pinned_icon'),

            // Optional short label shown in the nav menu instead of the (possibly
            // long) page title. Falls back to the title when blank. Rendered as a
            // text node by Mithril (escaped).
            Schema\Str::make('pinnedLabel')
                ->writable()
                ->nullable()
                ->maxLength(100)
                ->property('pinned_label'),

            // Hit counter, incremented once per view in ShowPageBySlugController.
            // Read-only over the API (never client-writable); shown in the admin
            // list and exposed to PHP pages as $page->view_count.
            Schema\Integer::make('viewCount')
                ->property('view_count'),

            // The destination of a "redirect" page, exposed publicly (a redirect's
            // target is not secret, unlike the visibility-gated raw `content`) so
            // the frontend can forward without needing edit rights. Null for every
            // other type, and null if the stored target is not a safe URL.
            Schema\Str::make('redirectUrl')
                ->get(fn (Page $page) => $page->content_type === 'redirect'
                    ? RedirectTarget::sanitize($page->content)
                    : null),

            // Whether a redirect page forwards immediately (true, default) or shows
            // a landing page with the link (false). Public-readable so the frontend
            // knows whether to auto-forward; writable only through the edit-gated
            // Update endpoint.
            Schema\Boolean::make('redirectImmediate')
                ->writable()
                ->property('redirect_immediate'),

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

        // Normalise an empty visible_groups array to NULL ("no restriction").
        // The `array` cast persists [] as the JSON string '[]', which the
        // visibility scope's whereNull()/whereJsonContains() checks would all
        // miss — silently hiding the page from everyone. NULL is the single
        // sentinel the scope understands, so coerce here on write.
        if (is_array($model->visible_groups) && count($model->visible_groups) === 0) {
            $model->visible_groups = null;
        }

        $type = $model->content_type ?: 'text';

        // A redirect page's content is its destination URL: reject anything that
        // isn't a plain http(s) URL or a root-relative path, so a redirect can
        // never carry a dangerous scheme (javascript:, data:, …) into an HTTP
        // redirect or a bare href.
        if ($type === 'redirect' && ! RedirectTarget::isSafe($model->content)) {
            throw new ValidationException([
                'content' => 'Enter a valid redirect URL — an https:// address or a path starting with "/".',
            ]);
        }

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

        // Only the parent_id assignment can introduce a cycle. When editing an
        // existing page without touching its parent (e.g. a content edit), there
        // is nothing to check — skip the lookups entirely. New models report
        // every attribute dirty, so creates are always checked.
        if ($model->exists && ! $model->isDirty('parent_id')) {
            return;
        }

        $selfId = (int) $model->id;

        // Pre-fetch the whole (id => parent_id) forest in one query and walk it
        // in memory, instead of issuing one SELECT per ancestor level.
        $parentOf = Page::query()->pluck('parent_id', 'id')->all();

        $ancestorId = (int) $model->parent_id;
        $seen = [];

        while ($ancestorId) {
            if ($selfId && $ancestorId === $selfId) {
                // A cycle is invalid input, not an authorisation failure — surface
                // it as a 422 on the parentId field, not a misleading 403.
                throw new ValidationException(['parentId' => 'A page cannot be its own parent or descendant.']);
            }

            if (isset($seen[$ancestorId])) {
                break; // pre-existing cycle in stored data — don't loop forever
            }
            $seen[$ancestorId] = true;

            $next = $parentOf[$ancestorId] ?? null;
            if ($next === null) {
                break;
            }
            $ancestorId = (int) $next;
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
