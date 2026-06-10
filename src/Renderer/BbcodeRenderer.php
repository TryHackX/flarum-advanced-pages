<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Formatter\Formatter;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use TryHackX\AdvancedPages\Page;

class BbcodeRenderer implements RendererInterface
{
    public function __construct(
        protected Formatter $formatter,
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator
    ) {
    }

    public function supports(string $contentType): bool
    {
        return $contentType === 'bbcode';
    }

    public function render(Page $page, ?User $actor = null, ?string $csrfToken = null): string
    {
        $content = $page->content;
        $newlineMode = $page->newline_mode ?? 'flarum';

        $marker = '§§APNL§§';
        if ($newlineMode === 'preserve') {
            $content = str_replace("\r\n", "\n", $content);

            $content = preg_replace_callback('#\[list(?:=[^\]]*)?]([\s\S]*?)\[/list\]#i', function ($m) {
                return str_replace("\n", '', $m[0]);
            }, $content);
            $content = preg_replace_callback('#\[table\]([\s\S]*?)\[/table\]#i', function ($m) {
                return str_replace("\n", '', $m[0]);
            }, $content);

            $content = str_replace("\n", $marker, $content);
        }

        $xml = $this->formatter->parse($content, null, null);
        $html = $this->formatter->render($xml);

        if ($newlineMode === 'preserve') {
            $html = str_replace($marker, '<br>', $html);
            $html = preg_replace('#<p>(.*?)</p>#s', '$1', $html);

            $html = preg_replace_callback('#<table[^>]*>.*?</table>#si', function ($m) {
                return str_replace('<br>', '', $m[0]);
            }, $html);
            $html = preg_replace_callback('#<[uo]l>.*?</[uo]l>#si', function ($m) {
                return str_replace('<br>', '', $m[0]);
            }, $html);
            $html = preg_replace('#(<(?:blockquote|details|summary|div)[^>]*>)<br>#i', '$1', $html);
            $html = preg_replace('#<br>(</(?:blockquote|details|summary|div)>)#i', '$1', $html);
            $html = preg_replace('#(</(?:pre|blockquote|details|ul|ol|table|div)>)<br>#i', '$1', $html);

            $html = preg_replace_callback('#<pre[^>]*><code[^>]*>(.*?)</code></pre>#s', function ($m) {
                return str_replace('<br>', "\n", $m[0]);
            }, $html);

            $html = preg_replace('#(<br>)+$#', '', $html);
        }

        if ($this->settings->get('tryhackx-advanced-pages.bbcode_url', false)) {
            $html = preg_replace_callback(
                '#\[url=(.*?)\](.*?)\[/url\]#i',
                function ($matches) {
                    $url = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                    $text = $matches[2];
                    if (preg_match('#^(javascript|data|vbscript):#i', $matches[1])) {
                        return $matches[0];
                    }
                    return '<a href="' . $url . '" rel="nofollow ugc noopener">' . $text . '</a>';
                },
                $html
            );
            $html = preg_replace_callback(
                '#\[url\](.*?)\[/url\]#i',
                function ($matches) {
                    $url = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                    if (preg_match('#^(javascript|data|vbscript):#i', $matches[1])) {
                        return $matches[0];
                    }
                    return '<a href="' . $url . '" rel="nofollow ugc noopener">' . $url . '</a>';
                },
                $html
            );
        }

        if (!$actor || !$actor->hasPermission('advancedPages.viewSpoilers')) {
            $html = $this->hideSpoilerContent($html);
        }

        return '<div class="AdvancedPages-bbcodeContent">' . $html . '</div>';
    }

    protected function hideSpoilerContent(string $html): string
    {
        $message = htmlspecialchars(
            $this->translator->trans('tryhackx-advanced-pages.forum.spoiler.locked'),
            ENT_QUOTES,
            'UTF-8'
        );
        $lockedBody = '<div class="AdvancedPages-spoilerContent">'
            . '<p class="AdvancedPages-spoilerLocked"><i class="fas fa-lock"></i> ' . $message . '</p>'
            . '</div>';

        // Match a whole spoiler <details>…</details> block. The class test matches
        // both the Advanced Pages template (class="AdvancedPages-spoiler") and
        // Flarum's default one (class="spoiler"), so gating works regardless of the
        // "Replace Forum Spoiler" setting. The body is replaced wholesale — only the
        // <summary> (title) is kept — so nothing inside can leak, even when the
        // content contains nested <div>s (which defeated the old first-</div> regex).
        return preg_replace_callback(
            '#<details\b[^>]*\bclass="[^"]*spoiler[^"]*"[^>]*>(.*?)</details>#is',
            function (array $m) use ($lockedBody) {
                $openTag = substr($m[0], 0, strpos($m[0], '>') + 1);

                $summary = '';
                if (preg_match('#<summary\b[^>]*>.*?</summary>#is', $m[1], $sm)) {
                    $summary = $sm[0];
                }

                return $openTag . $summary . $lockedBody . '</details>';
            },
            $html
        );
    }
}
