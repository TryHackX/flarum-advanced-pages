<?php

namespace TryHackX\AdvancedPages\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use TryHackX\AdvancedPages\Api\Serializer\PageSerializer;
use TryHackX\AdvancedPages\PageRepository;

class ShowPageController extends AbstractShowController
{
    public $serializer = PageSerializer::class;

    public $include = ['user', 'editUser'];

    public function __construct(
        protected PageRepository $pages
    ) {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        if (is_numeric($id)) {
            $page = $this->pages->findOrFail((int) $id, $actor);
        } else {
            $page = $this->pages->findBySlug($id, $actor);
        }

        // Flag for serializer to render HTML
        $page->renderHtml = true;

        return $page;
    }
}
