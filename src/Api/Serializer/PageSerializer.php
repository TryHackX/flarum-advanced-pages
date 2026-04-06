<?php

namespace TryHackX\AdvancedPages\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\Renderer\PageRenderer;

class PageSerializer extends AbstractSerializer
{
    protected $type = 'advanced-pages';

    protected function getDefaultAttributes($page): array
    {
        $attributes = [
            'title'           => $page->title,
            'slug'            => $page->slug,
            'contentType'     => $page->content_type,
            'newlineMode'     => $page->newline_mode,
            'isPublished'     => (bool) $page->is_published,
            'isHidden'        => (bool) $page->is_hidden,
            'isRestricted'    => (bool) $page->is_restricted,
            'metaDescription' => $page->meta_description,
            'visibleGroups'   => $page->visible_groups,
            'createdAt'       => $this->formatDate($page->created_at),
            'updatedAt'       => $this->formatDate($page->updated_at),
        ];

        if ($this->getActor()->isAdmin()) {
            $attributes['content'] = $page->content;
        }

        // Render HTML for single-page views (not list)
        if (! empty($page->renderHtml)) {
            $attributes['contentHtml'] = resolve(PageRenderer::class)->render($page, $this->getActor());
        }

        return $attributes;
    }

    protected function user($page)
    {
        return $this->hasOne($page, BasicUserSerializer::class);
    }

    protected function editUser($page)
    {
        return $this->hasOne($page, BasicUserSerializer::class);
    }
}
