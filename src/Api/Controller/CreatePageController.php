<?php

namespace TryHackX\AdvancedPages\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use TryHackX\AdvancedPages\Api\Serializer\PageSerializer;
use TryHackX\AdvancedPages\Event\PageCreated;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\PageValidator;

class CreatePageController extends AbstractCreateController
{
    public $serializer = PageSerializer::class;

    public $include = ['user', 'editUser'];

    public function __construct(
        protected PageValidator $validator
    ) {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('createAdvancedPages');

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $page = new Page();
        $page->user_id = $actor->id;
        $page->title = Arr::get($data, 'title', '');
        $page->slug = Arr::get($data, 'slug', '');
        $page->content = Arr::get($data, 'content', '');
        $page->content_type = Arr::get($data, 'contentType', 'html');
        $page->newline_mode = Arr::get($data, 'newlineMode', 'flarum');
        $page->is_published = (bool) Arr::get($data, 'isPublished', false);
        $page->is_hidden = (bool) Arr::get($data, 'isHidden', false);
        $page->is_restricted = (bool) Arr::get($data, 'isRestricted', false);
        $page->meta_description = Arr::get($data, 'metaDescription');
        $page->visible_groups = Arr::get($data, 'visibleGroups');

        if ($page->content_type === 'php') {
            $actor->assertAdmin();
        }

        $this->validator->assertValid($page->getAttributes());

        $page->save();

        event(new PageCreated($page, $actor));

        $page->renderHtml = true;

        return $page;
    }
}
