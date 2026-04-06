<?php

namespace TryHackX\AdvancedPages\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use TryHackX\AdvancedPages\Api\Serializer\PageSerializer;
use TryHackX\AdvancedPages\Event\PageUpdated;
use TryHackX\AdvancedPages\PageRepository;
use TryHackX\AdvancedPages\PageValidator;

class UpdatePageController extends AbstractShowController
{
    public $serializer = PageSerializer::class;

    public $include = ['user', 'editUser'];

    public function __construct(
        protected PageRepository $pages,
        protected PageValidator $validator
    ) {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        $page = $this->pages->findOrFail((int) $id, $actor);

        $actor->assertCan('edit', $page);

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        if (Arr::has($data, 'title')) {
            $page->title = Arr::get($data, 'title');
        }
        if (Arr::has($data, 'slug')) {
            $page->slug = Arr::get($data, 'slug');
        }
        if (Arr::has($data, 'content')) {
            $page->content = Arr::get($data, 'content');
        }
        if (Arr::has($data, 'contentType')) {
            $newType = Arr::get($data, 'contentType');
            if ($newType === 'php') {
                $actor->assertAdmin();
            }
            if ($page->content_type === 'php') {
                $actor->assertAdmin();
            }
            $page->content_type = $newType;
        }
        if (Arr::has($data, 'newlineMode')) {
            $page->newline_mode = Arr::get($data, 'newlineMode');
        }
        if (Arr::has($data, 'isPublished')) {
            $page->is_published = (bool) Arr::get($data, 'isPublished');
        }
        if (Arr::has($data, 'isHidden')) {
            $page->is_hidden = (bool) Arr::get($data, 'isHidden');
        }
        if (Arr::has($data, 'isRestricted')) {
            $page->is_restricted = (bool) Arr::get($data, 'isRestricted');
        }
        if (Arr::has($data, 'metaDescription')) {
            $page->meta_description = Arr::get($data, 'metaDescription');
        }
        if (Arr::has($data, 'visibleGroups')) {
            $page->visible_groups = Arr::get($data, 'visibleGroups');
        }

        $page->edit_user_id = $actor->id;

        $this->validator->assertValid($page->getAttributes());

        $page->save();

        event(new PageUpdated($page, $actor));

        $page->renderHtml = true;

        return $page;
    }
}
