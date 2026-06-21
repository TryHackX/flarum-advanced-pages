<?php

namespace TryHackX\AdvancedPages;

use Flarum\Settings\SettingsRepositoryInterface;
use s9e\TextFormatter\Configurator;

/**
 * Registers this extension's custom BBCode tags ([table], [spoiler], [center])
 * on the s9e formatter, gated by their admin settings.
 *
 * Implemented as an invokable class with the settings repository injected via
 * the constructor — the idiomatic Flarum way — instead of reaching into the
 * container with resolve() inside a configure() closure.
 */
class FormatterConfigurator
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function __invoke(Configurator $configurator): void
    {
        $settings = $this->settings;

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

            if ($settings->get('tryhackx-advanced-pages.replace_forum_spoiler')) {
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
            } else {
                $tag->template =
                    '<details class="spoiler" data-s9e-livepreview-ignore-attrs="open"><xsl:apply-templates/></details>';
            }

            $bbcode = $configurator->BBCodes->add('SPOILER');
            $bbcode->defaultAttribute = 'title';
        }

        if ($settings->get('tryhackx-advanced-pages.bbcode_center', true)) {
            $configurator->BBCodes->addCustom('[center]{TEXT}[/center]', '<div style="text-align:center">{TEXT}</div>');
        }
    }
}
