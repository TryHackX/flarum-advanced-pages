<?php

namespace TryHackX\AdvancedPages\Content;

use Flarum\Api\Client;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;

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
        }

        $document->payload['apiDocument'] = $apiDocument;

        return $document;
    }

    protected function getApiDocument(Request $request, string $slug): object
    {
        return json_decode(
            $this->api
                ->withParentRequest($request)
                ->get("/advanced-pages/$slug")
                ->getBody()
        );
    }
}
