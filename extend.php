<?php

use Flarum\Extend;
use Flarum\Frontend\Controller as FrontendController;
use Illuminate\Contracts\Container\Container;
use TryHackX\AdvancedPages\Access;
use TryHackX\AdvancedPages\Api\Controller\ShowPageBySlugController;
use TryHackX\AdvancedPages\Api\Resource\PageResource;
use TryHackX\AdvancedPages\Console\PermissionCommand;
use TryHackX\AdvancedPages\Content\PageContent;
use TryHackX\AdvancedPages\FormatterConfigurator;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\Provider\AdvancedPagesServiceProvider;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/resources/less/admin.less'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less')
        ->route('/p/{slug:.+}', 'tryhackx-advanced-pages.page', PageContent::class),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    new Extend\ApiResource(PageResource::class),

    // Look a page up by its full (possibly nested, slash-containing) slug — the
    // standard Show endpoint can only address a single-segment {id}.
    (new Extend\Routes('api'))
        ->get('/advanced-pages-by-slug/{slug:.+}', 'tryhackx-advanced-pages.by-slug', ShowPageBySlugController::class),

    // Accept POST to /p/{slug} so PHP pages can handle form submissions and file
    // uploads ($_POST / $_FILES). It re-renders the same frontend document as the
    // GET route (mirrors Extend\Frontend's GET handler). CSRF is still enforced
    // by the forum middleware — page forms must submit the `csrfToken` field,
    // which is exposed to PHP pages as the $csrfToken variable.
    (new Extend\Routes('forum'))
        ->post('/p/{slug:.+}', 'tryhackx-advanced-pages.page.post', function (Container $container) {
            return new FrontendController(
                $container->make('flarum.frontend.forum', ['content' => PageContent::class])
            );
        }),

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
        }),
];
