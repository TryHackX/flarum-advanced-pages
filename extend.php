<?php

use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use TryHackX\AdvancedPages\Access;
use TryHackX\AdvancedPages\Api\Controller\ShowPageBySlugController;
use TryHackX\AdvancedPages\Api\Resource\PageResource;
use TryHackX\AdvancedPages\Console\PermissionCommand;
use TryHackX\AdvancedPages\FormatterConfigurator;
use TryHackX\AdvancedPages\Http\PageFormController;
use TryHackX\AdvancedPages\Http\PageViewController;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\PinnedPages;
use TryHackX\AdvancedPages\Provider\AdvancedPagesServiceProvider;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/resources/less/admin.less'),

    // Forum assets. The GET /p/{slug} route itself is registered below via
    // Extend\Routes → PageViewController, which can short-circuit a "redirect"
    // page into a real HTTP 302 before the frontend renders.
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    new Extend\ApiResource(PageResource::class),

    // Expose the pages pinned to the index-sidebar navigation on the forum
    // payload, so the forum JS can render the menu links without an extra
    // request. Already scoped to the viewer's visibility and ordered like the
    // admin page list; an empty array when nothing is pinned.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Arr::make('advancedPagesPinned')
                ->get(fn ($model, Context $context) => (new PinnedPages($context->getActor()))->forNav()),
        ]),

    // Look a page up by its full (possibly nested, slash-containing) slug — the
    // standard Show endpoint can only address a single-segment {id}.
    (new Extend\Routes('api'))
        ->get('/advanced-pages-by-slug/{slug:.+}', 'tryhackx-advanced-pages.by-slug', ShowPageBySlugController::class),

    (new Extend\Routes('forum'))
        // GET renders the page — or, for a "redirect" page in immediate mode,
        // issues a real HTTP 302 (after counting the visit). See PageViewController.
        ->get('/p/{slug:.+}', 'tryhackx-advanced-pages.page', PageViewController::class)
        // Accept POST to /p/{slug} so PHP pages can handle form submissions and file
        // uploads ($_POST / $_FILES). PageFormController re-renders the same frontend
        // document as the GET route via Flarum's RouteHandlerFactory. CSRF is still
        // enforced by the forum middleware — page forms must submit the `csrfToken`
        // field, which is exposed to PHP pages as the $csrfToken variable.
        ->post('/p/{slug:.+}', 'tryhackx-advanced-pages.page.post', PageFormController::class),

    (new Extend\ModelVisibility(Page::class))
        ->scope(Access\ScopePageVisibility::class),

    (new Extend\Policy())
        ->modelPolicy(Page::class, Access\PagePolicy::class),

    (new Extend\ServiceProvider())
        ->register(AdvancedPagesServiceProvider::class),

    (new Extend\Console())
        ->command(PermissionCommand::class),

    (new Extend\Formatter())
        ->configure(FormatterConfigurator::class),

    (new Extend\Settings())
        ->default('tryhackx-advanced-pages.bbcode_table', true)
        ->default('tryhackx-advanced-pages.bbcode_spoiler', true)
        ->default('tryhackx-advanced-pages.bbcode_center', true)
        ->default('tryhackx-advanced-pages.bbcode_url', false)
        ->default('tryhackx-advanced-pages.replace_forum_spoiler', false)
        ->serializeToForum('tryhackx-advanced-pages.replace_forum_spoiler', 'tryhackx-advanced-pages.replace_forum_spoiler', function ($value) {
            return (bool) $value;
        })
        ->default('tryhackx-advanced-pages.code_buttons_in_posts', false)
        ->serializeToForum('tryhackx-advanced-pages.code_buttons_in_posts', 'tryhackx-advanced-pages.code_buttons_in_posts', function ($value) {
            return (bool) $value;
        }),
];
