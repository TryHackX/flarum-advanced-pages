<?php

namespace TryHackX\AdvancedPages\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;
use TryHackX\AdvancedPages\Event;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\Renderer\PageRenderer;

/**
 * @extends AbstractDatabaseResource<Page>
 */
class PageResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'advanced-pages';
    }

    public function model(): string
    {
        return Page::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function find(string $id, Context $context): ?object
    {
        if (is_numeric($id)) {
            return $this->query($context)->find($id);
        }

        return $this->query($context)->where('slug', $id)->first();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('-createdAt')
                ->paginate(20, 50),
            Endpoint\Show::make(),
            Endpoint\Create::make()
                ->authenticated()
                ->can('createAdvancedPages'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('edit'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(200),

            Schema\Str::make('slug')
                ->requiredOnCreate()
                ->writable()
                ->unique('advanced_pages', 'slug', true)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/i')
                ->maxLength(200),

            Schema\Str::make('content')
                ->requiredOnCreate()
                ->writable()
                ->visible(fn ($page, FlarumContext $context) => $context->getActor()->isAdmin()),

            Schema\Str::make('contentType')
                ->requiredOnCreate()
                ->writable()
                ->property('content_type')
                ->in(['html', 'php', 'text', 'bbcode', 'markdown']),

            Schema\Str::make('contentHtml')
                ->get(function (Page $page, FlarumContext $context) {
                    if ($context->endpoint instanceof Endpoint\Index) {
                        return null;
                    }

                    return resolve(PageRenderer::class)->render($page, $context->getActor());
                }),

            Schema\Str::make('newlineMode')
                ->writable()
                ->property('newline_mode')
                ->in(['flarum', 'preserve']),

            Schema\Boolean::make('isPublished')
                ->writable()
                ->property('is_published'),

            Schema\Boolean::make('isHidden')
                ->writable()
                ->property('is_hidden'),

            Schema\Boolean::make('isRestricted')
                ->writable()
                ->property('is_restricted'),

            Schema\Str::make('metaDescription')
                ->writable()
                ->nullable()
                ->maxLength(500)
                ->property('meta_description'),

            Schema\Arr::make('visibleGroups')
                ->writable()
                ->nullable()
                ->property('visible_groups'),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),

            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('editUser')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
            SortColumn::make('title'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        $actor = $context->getActor();

        $model->user_id = $actor->id;

        $contentType = $context->body()['attributes']['contentType'] ?? null;
        if ($contentType === 'php') {
            $actor->assertAdmin();
        }

        return $model;
    }

    public function saving(object $model, Context $context): ?object
    {
        $actor = $context->getActor();

        if (! $context->creating(self::class)) {
            $model->edit_user_id = $actor->id;

            if ($model->isDirty('content_type') && $model->content_type === 'php') {
                $actor->assertAdmin();
            }

            if ($model->getOriginal('content_type') === 'php') {
                $actor->assertAdmin();
            }
        }

        return $model;
    }

    public function created(object $model, Context $context): ?object
    {
        $this->events->dispatch(new Event\PageCreated($model, $context->getActor()));

        return $model;
    }

    public function saved(object $model, Context $context): ?object
    {
        if (! $context->creating(self::class)) {
            $this->events->dispatch(new Event\PageUpdated($model, $context->getActor()));
        }

        return $model;
    }

    public function deleting(object $model, Context $context): void
    {
        $this->events->dispatch(new Event\PageDeleted($model, $context->getActor()));
    }
}
