/**
 * Make editor-preview HTML inert before it is inserted with m.trust().
 *
 * The admin preview of BBCode / Markdown / text pages is produced in the browser
 * (a light BBCode converter and `marked`, which passes raw HTML through), while the
 * forum renders the same pages with Flarum's formatter, which escapes everything.
 * Without this, a page written by a non-admin author could run JavaScript in the
 * session of whoever opens its preview (e.g. `[img]x" onerror="…[/img]`).
 *
 * The string is parsed in an inert document (DOMParser — nothing loads, no event
 * fires), then active content is removed: script-capable elements, every on*
 * handler and URL attributes with a scheme other than http(s) / mailto.
 */
const DROPPED_TAGS = new Set([
  'SCRIPT', 'IFRAME', 'FRAME', 'FRAMESET', 'OBJECT', 'EMBED', 'APPLET', 'BASE', 'LINK', 'META', 'FORM',
  'ANIMATE', 'ANIMATEMOTION', 'ANIMATETRANSFORM', 'SET', 'HANDLER',
]);
const URL_ATTRIBUTES = new Set(['href', 'src', 'xlink:href', 'action', 'formaction', 'poster', 'background', 'srcset', 'data']);
// Absolute http(s) / mailto, or a scheme-less (relative) reference.
const SAFE_URL = /^(?:https?:|mailto:|[^:]*$)/i;

export default function sanitizePreview(html) {
  const doc = new DOMParser().parseFromString('<!DOCTYPE html><body>' + String(html || ''), 'text/html');

  doc.body.querySelectorAll('*').forEach((el) => {
    if (DROPPED_TAGS.has(el.tagName.toUpperCase())) {
      el.remove();
      return;
    }

    Array.from(el.attributes).forEach((attr) => {
      const name = attr.name.toLowerCase();
      // Browsers ignore whitespace and control characters inside a scheme
      // (" java\tscript:"), so test the value with all of them removed.
      const value = attr.value.replace(/[\u0000- \u007f]/g, '');

      if (name.startsWith('on') || (URL_ATTRIBUTES.has(name) && !SAFE_URL.test(value))) {
        el.removeAttribute(attr.name);
      }
    });
  });

  return doc.body.innerHTML;
}
