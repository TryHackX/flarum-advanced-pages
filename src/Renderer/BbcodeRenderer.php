<?php

namespace TryHackX\AdvancedPages\Renderer;

use Flarum\Formatter\Formatter;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use TryHackX\AdvancedPages\Page;

class BbcodeRenderer implements RendererInterface
{
    /** How long a rendered page stays cached (1 week). Edits bust it via the key. */
    protected const CACHE_TTL = 604800;

    public function __construct(
        protected Formatter $formatter,
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator,
        protected Cache $cache
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

        // Cache the (expensive) s9e parse+render. It's a pure function of the
        // string passed to parse() — which already bakes in the newline mode via
        // $content — so the content hash is a complete key. The actor-dependent
        // bits (spoiler gating) and the cheap [url] post-processing run per request
        // below, outside the cache. Edits change $content → new key; a formatter
        // setting change is followed by `php flarum cache:clear`, which wipes this.
        $html = $this->cache->remember(
            'tryhackx-advanced-pages.render.bb.' . sha1($content),
            self::CACHE_TTL,
            fn () => $this->formatter->render($this->formatter->parse($content, null, null))
        );

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
        // Fast path: no spoilers, nothing to gate.
        if (stripos($html, 'spoiler') === false) {
            return $html;
        }

        $message = htmlspecialchars(
            $this->translator->trans('tryhackx-advanced-pages.forum.spoiler.locked'),
            ENT_QUOTES,
            'UTF-8'
        );
        $lockedBody = '<div class="AdvancedPages-spoilerContent">'
            . '<p class="AdvancedPages-spoilerLocked"><i class="fas fa-lock"></i> ' . $message . '</p>'
            . '</div>';

        // Tokenise every <details>/</details> tag (these only ever come from the
        // spoiler BBCode — the class test matches both the Advanced Pages template
        // class="AdvancedPages-spoiler" and Flarum's default class="spoiler", so
        // gating works regardless of the "Replace Forum Spoiler" setting).
        //
        // We then walk the tokens with a depth counter to find the matching close
        // of each *outermost* spoiler and replace its whole body (keeping only the
        // <summary> title). This is correct even for nested spoilers — the previous
        // single non-greedy regex stopped at the first </details>, leaking the rest
        // of an outer spoiler that contained a nested one. Non-spoiler HTML is left
        // byte-for-byte untouched, and the scan is linear.
        if (! preg_match_all('#</?details\b[^>]*>#i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $tokens = $matches[0]; // each entry: [tagText, byteOffset]
        $n = count($tokens);

        $out = '';
        $lastPos = 0;
        $i = 0;

        while ($i < $n) {
            [$tag, $offset] = $tokens[$i];
            $isSpoiler = $tag[1] !== '/'
                && preg_match('#\bclass="[^"]*spoiler[^"]*"#i', $tag);

            if (! $isSpoiler) {
                $i++;
                continue;
            }

            // Find the matching close, counting nested <details> of any kind.
            $depth = 0;
            $j = $i;
            for (; $j < $n; $j++) {
                $depth += ($tokens[$j][0][1] === '/') ? -1 : 1;
                if ($depth === 0) {
                    break;
                }
            }
            if ($j >= $n) {
                break; // unbalanced markup — leave the remainder as-is
            }

            $openTagEnd = $offset + strlen($tag);
            $closeTag = $tokens[$j][0];
            $closeStart = $tokens[$j][1];
            $blockEnd = $closeStart + strlen($closeTag);

            $body = substr($html, $openTagEnd, $closeStart - $openTagEnd);
            $summary = '';
            if (preg_match('#<summary\b[^>]*>.*?</summary>#is', $body, $sm)) {
                $summary = $sm[0];
            }

            $out .= substr($html, $lastPos, $offset - $lastPos);
            $out .= $tag . $summary . $lockedBody . $closeTag;
            $lastPos = $blockEnd;

            $i = $j + 1; // skip everything inside this block (nested spoilers included)
        }

        $out .= substr($html, $lastPos);

        return $out;
    }
}
