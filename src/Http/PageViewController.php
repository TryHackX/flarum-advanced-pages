<?php

namespace TryHackX\AdvancedPages\Http;

use Flarum\Http\RequestUtil;
use Flarum\Http\RouteHandlerFactory;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TryHackX\AdvancedPages\Content\PageContent;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\RedirectTarget;

/**
 * Handles GET /p/{slug}.
 *
 * A "redirect" page set to forward immediately is turned into a real HTTP 302
 * redirect right here — after counting the visit — instead of loading the SPA and
 * bouncing client-side. Everything else (normal pages, and redirect pages set to
 * show a brief landing before forwarding) falls through to the usual forum
 * frontend render, exactly as the default GET route would.
 */
class PageViewController implements RequestHandlerInterface
{
    public function __construct(
        protected RouteHandlerFactory $routeHandlerFactory
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $slug = (string) Arr::get($request->getQueryParams(), 'slug', '');
        $actor = RequestUtil::getActor($request);

        // One indexed point-lookup (unique slug), narrowed to redirect pages so it
        // returns nothing for the common case (a normal page), scoped to what the
        // viewer may see so hidden/draft redirects don't leak a forward.
        $page = Page::query()
            ->whereVisibleTo($actor)
            ->where('slug', $slug)
            ->where('content_type', 'redirect')
            ->first();

        if ($page && $page->redirect_immediate) {
            $target = RedirectTarget::sanitize($page->content);

            if ($target !== null) {
                // Count the visit *before* forwarding — an atomic +1 that never
                // touches updated_at (mirrors ShowPageBySlugController; the by-slug
                // view path is skipped when we redirect here, so no double count).
                $page->newQuery()->toBase()
                    ->where('id', $page->id)
                    ->update(['view_count' => new Expression('view_count + 1')]);

                // 302 (temporary), not 301 — a page's target can change or be
                // unpublished, and browsers cache 301s aggressively.
                return new RedirectResponse($target, 302);
            }
        }

        // Normal page, or a redirect that shows a landing first: render the forum
        // frontend via Flarum's documented factory (same as the default GET route).
        return ($this->routeHandlerFactory->toForum(PageContent::class))($request, []);
    }
}
