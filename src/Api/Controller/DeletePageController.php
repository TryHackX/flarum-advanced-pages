<?php

namespace TryHackX\AdvancedPages\Api\Controller;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use TryHackX\AdvancedPages\Event\PageDeleted;
use TryHackX\AdvancedPages\PageRepository;

class DeletePageController extends AbstractDeleteController
{
    public function __construct(
        protected PageRepository $pages
    ) {
    }

    protected function delete(ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        $page = $this->pages->findOrFail((int) $id, $actor);

        $actor->assertCan('delete', $page);

        event(new PageDeleted($page, $actor));

        $page->delete();
    }
}
