<?php

namespace TryHackX\AdvancedPages\Api\Controller;

use Flarum\Api\Client;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TryHackX\AdvancedPages\Page;

/**
 * Resolve a page by its (possibly slash-containing) slug and return the same
 * JSON:API document the Show endpoint would.
 *
 * The json-api-server Show endpoint addresses resources by a single-segment
 * {id}, so nested slugs like "docs/getting-started" can't reach it directly.
 * Here the slug — captured by a {slug:.+} route — is resolved to the page
 * (respecting visibility), then proxied to the standard Show endpoint by numeric
 * id, so the response (including the rendered contentHtml) is identical.
 */
class ShowPageBySlugController implements RequestHandlerInterface
{
    public function __construct(
        protected Client $api
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $slug = (string) Arr::get($request->getQueryParams(), 'slug', '');
        $actor = RequestUtil::getActor($request);

        $page = Page::query()
            ->whereVisibleTo($actor)
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            throw (new ModelNotFoundException())->setModel(Page::class);
        }

        // Count this view. A single atomic UPDATE (view_count = view_count + 1) via
        // the base query builder — no read-modify-write race, and no touching of
        // updated_at (which tracks content edits, not visits). This is the one
        // genuine "view" path (the admin list uses the Index endpoint, never this),
        // and a full page load reuses the server-preloaded document, so a view is
        // counted exactly once. `column + 1` stays cross-database.
        $page->newQuery()->toBase()
            ->where('id', $page->id)
            ->update(['view_count' => new Expression('view_count + 1')]);

        return $this->api
            ->withParentRequest($request)
            ->get('/advanced-pages/' . $page->id);
    }
}
