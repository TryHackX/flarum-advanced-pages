<?php

namespace TryHackX\AdvancedPages\Content;

use Flarum\Api\Client;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;
use TryHackX\AdvancedPages\Renderer\RedirectRenderer;

class PageContent
{
    public function __construct(
        protected Client $api
    ) {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $slug = Arr::get($request->getQueryParams(), 'slug', '');

        $apiDocument = $this->getApiDocument($request, $slug);

        if (isset($apiDocument->data)) {
            $title = $apiDocument->data->attributes->title ?? '';
            $metaDescription = $apiDocument->data->attributes->metaDescription ?? '';

            if ($title) {
                $document->title = $title;
            }

            if ($metaDescription) {
                $document->meta['description'] = $metaDescription;
            }

            // Redirect pages forward via a head meta-refresh on the initial (full)
            // page load, so it works even without JavaScript. Immediate pages are
            // normally already turned into a real 302 by PageViewController before
            // reaching here, so in practice this fires for the landing mode with the
            // countdown delay — the visitor sees the page, then forwards. (delay = 0
            // for immediate is kept as a belt-and-suspenders fallback.) The client
            // (PageView) runs the visible countdown and also forwards, covering
            // in-app SPA navigation. redirectUrl is server-validated (http(s) or
            // root-relative) and HTML-escaped here, so it cannot break out of the
            // attribute or carry a dangerous scheme.
            $redirectUrl = $apiDocument->data->attributes->redirectUrl ?? null;
            $redirectImmediate = $apiDocument->data->attributes->redirectImmediate ?? true;
            if (is_string($redirectUrl) && $redirectUrl !== '') {
                $delay = $redirectImmediate ? 0 : RedirectRenderer::COUNTDOWN_SECONDS;
                $escaped = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
                $document->head[] = '<meta http-equiv="refresh" content="' . $delay . '; url=' . $escaped . '">';
            }
        }

        $document->payload['apiDocument'] = $apiDocument;

        return $document;
    }

    protected function getApiDocument(Request $request, string $slug): object
    {
        // Resolve via the by-slug route so nested slugs (with slashes) work — the
        // Show endpoint's {id} segment cannot contain slashes. The response is a
        // normal single-resource document (incl. rendered contentHtml).
        return json_decode(
            $this->api
                ->withParentRequest($request)
                ->get('/advanced-pages-by-slug/' . $slug)
                ->getBody()
        );
    }
}
