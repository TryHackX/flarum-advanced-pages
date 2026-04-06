<?php

use Flarum\Extend;
use TryHackX\AdvancedPages\Access;
use TryHackX\AdvancedPages\Api\Controller;
use TryHackX\AdvancedPages\Content\PageContent;
use TryHackX\AdvancedPages\Page;
use TryHackX\AdvancedPages\Provider\AdvancedPagesServiceProvider;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/resources/less/admin.less'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less')
        ->route('/p/{slug}', 'tryhackx-advanced-pages.page', PageContent::class),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    (new Extend\Routes('api'))
        ->get('/advanced-pages', 'advanced-pages.index', Controller\ListPagesController::class)
        ->get('/advanced-pages/{id}', 'advanced-pages.show', Controller\ShowPageController::class)
        ->post('/advanced-pages', 'advanced-pages.create', Controller\CreatePageController::class)
        ->patch('/advanced-pages/{id}', 'advanced-pages.update', Controller\UpdatePageController::class)
        ->delete('/advanced-pages/{id}', 'advanced-pages.delete', Controller\DeletePageController::class),

    (new Extend\ModelVisibility(Page::class))
        ->scope(Access\ScopePageVisibility::class),

    (new Extend\Policy())
        ->modelPolicy(Page::class, Access\PagePolicy::class),

    (new Extend\ServiceProvider())
        ->register(AdvancedPagesServiceProvider::class),

    (new Extend\Formatter())
        ->configure(function (\s9e\TextFormatter\Configurator $configurator) {
            $settings = resolve('flarum.settings');

            if ($settings->get('tryhackx-advanced-pages.bbcode_table', true)) {
                $configurator->BBCodes->addCustom('[table]{TEXT}[/table]', '<table class="AdvancedPages-table">{TEXT}</table>');
                $configurator->BBCodes->addCustom('[tr]{TEXT}[/tr]', '<tr>{TEXT}</tr>');
                $configurator->BBCodes->addCustom('[th]{TEXT}[/th]', '<th>{TEXT}</th>');
                $configurator->BBCodes->addCustom('[td]{TEXT}[/td]', '<td>{TEXT}</td>');
            }

            if ($settings->get('tryhackx-advanced-pages.bbcode_spoiler', true)) {
                $tag = $configurator->tags->add('SPOILER');
                $tag->attributes->add('title');
                $tag->attributes['title']->required = false;
                $tag->template =
                    '<details class="AdvancedPages-spoiler" data-spoiler="1">' .
                        '<summary>' .
                            '<span class="AdvancedPages-spoilerIcon"><i class="fas fa-eye"></i></span> ' .
                            '<span class="AdvancedPages-spoilerTitle">' .
                                '<xsl:choose>' .
                                    '<xsl:when test="@title">Spoiler: <xsl:value-of select="@title"/></xsl:when>' .
                                    '<xsl:otherwise>Spoiler</xsl:otherwise>' .
                                '</xsl:choose>' .
                            '</span>' .
                        '</summary>' .
                        '<div class="AdvancedPages-spoilerContent"><xsl:apply-templates/></div>' .
                    '</details>';

                $bbcode = $configurator->BBCodes->add('SPOILER');
                $bbcode->defaultAttribute = 'title';
            }

            if ($settings->get('tryhackx-advanced-pages.bbcode_center', true)) {
                $configurator->BBCodes->addCustom('[center]{TEXT}[/center]', '<div style="text-align:center">{TEXT}</div>');
            }
        }),

    (new Extend\Settings())
        ->default('tryhackx-advanced-pages.bbcode_table', true)
        ->default('tryhackx-advanced-pages.bbcode_spoiler', true)
        ->default('tryhackx-advanced-pages.bbcode_center', true)
        ->default('tryhackx-advanced-pages.bbcode_url', false),
];
