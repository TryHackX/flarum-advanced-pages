<?php

namespace TryHackX\AdvancedPages\Http;

use Flarum\Http\RouteHandlerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TryHackX\AdvancedPages\Content\PageContent;

/**
 * Handles POST /p/{slug} so PHP pages can process form submissions and file
 * uploads ($_POST / $_FILES). It re-renders the very same forum frontend
 * document as the GET route.
 *
 * The frontend is built through Flarum's own {@see RouteHandlerFactory::toForum()}
 * — the documented factory that Extend\Frontend uses to register the GET route —
 * rather than reaching into the internal `flarum.frontend.forum` container
 * binding by name. That keeps this route resilient to core refactors of that
 * binding and makes the handler unit-testable (the factory is injected).
 *
 * CSRF is still enforced by the forum middleware: page forms must submit the
 * `csrfToken` field, which is exposed to PHP pages as the $csrfToken variable.
 */
class PageFormController implements RequestHandlerInterface
{
    public function __construct(
        protected RouteHandlerFactory $routeHandlerFactory
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Extend\Routes has already merged the {slug} route parameter into the
        // request's query params (that is how the GET route exposes it too), so
        // PageContent can read it — no extra route params are needed here.
        return ($this->routeHandlerFactory->toForum(PageContent::class))($request, []);
    }
}
