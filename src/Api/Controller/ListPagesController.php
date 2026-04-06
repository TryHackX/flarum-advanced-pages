<?php

namespace TryHackX\AdvancedPages\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use TryHackX\AdvancedPages\Api\Serializer\PageSerializer;
use TryHackX\AdvancedPages\PageRepository;

class ListPagesController extends AbstractListController
{
    public $serializer = PageSerializer::class;

    public $include = ['user', 'editUser'];

    public $sortFields = ['createdAt', 'title'];

    public $sort = ['createdAt' => 'desc'];

    public function __construct(
        protected PageRepository $pages,
        protected UrlGenerator $url
    ) {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);
        $sort = $this->extractSort($request);

        $query = $this->pages->query()->whereVisibleTo($actor);

        foreach ($sort as $field => $order) {
            $column = match ($field) {
                'createdAt' => 'created_at',
                default => $field,
            };
            $query->orderBy($column, $order);
        }

        $total = $query->count();

        $results = $query->skip($offset)->take($limit)->get();

        $document->addPaginationLinks(
            $this->url->to('api')->route('advanced-pages.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $total
        );

        $document->setMeta(['total' => $total]);

        return $results;
    }
}
