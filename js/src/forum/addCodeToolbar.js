import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

// Selects the entire contents of a code block (the "Select" button).
function selectCode(code) {
  const sel = window.getSelection();
  if (!sel) return;
  const range = document.createRange();
  range.selectNodeContents(code);
  sel.removeAllRanges();
  sel.addRange(range);
}

function flashCopied(btn, label) {
  btn.textContent = extractText(app.translator.trans('tryhackx-advanced-pages.forum.code.copied'));
  btn.classList.add('is-copied');
  clearTimeout(btn._apTimer);
  btn._apTimer = setTimeout(() => {
    btn.textContent = label;
    btn.classList.remove('is-copied');
  }, 1500);
}

function fallbackCopy(text, done) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.top = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    done();
  } catch (e) {
    // ignore — clipboard simply unavailable
  }
  document.body.removeChild(ta);
}

// Copies the current selection if it sits inside this code block, otherwise the
// whole block (the "Copy" button — copies everything when nothing is selected).
function copyCode(code, btn, label) {
  const sel = window.getSelection();
  let text = '';
  if (sel && sel.rangeCount && !sel.isCollapsed && code.contains(sel.anchorNode) && code.contains(sel.focusNode)) {
    text = sel.toString();
  }
  if (!text) text = code.textContent;

  const done = () => flashCopied(btn, label);
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done, () => fallbackCopy(text, done));
  } else {
    fallbackCopy(text, done);
  }
}

// Position the toolbar just left of the code block's vertical scrollbar. The
// scroll container is the <pre> on Advanced Pages (we cap it there) but the
// <code> on forum posts (a theme caps that), and either may be inset by padding
// — so position from the actual scrollbar edge rather than a fixed value. With
// mobile overlay scrollbars there is no scrollbar, so it falls back to the CSS
// default (6px). Re-run on resize: a vh-based cap and the scrollbar can both
// appear or change after first paint.
function positionToolbar(wrap, pre, code, bar) {
  let scrollEl = null;
  for (const el of [pre, code]) {
    if (el && el.offsetWidth - el.clientWidth > 0 && el.scrollHeight - el.clientHeight > 1) {
      scrollEl = el;
      break;
    }
  }
  if (!scrollEl) {
    bar.style.right = '';
    return;
  }
  const scrollbarWidth = scrollEl.offsetWidth - scrollEl.clientWidth;
  const scrollbarLeft = scrollEl.getBoundingClientRect().right - scrollbarWidth;
  const wrapRight = wrap.getBoundingClientRect().right;
  bar.style.right = Math.max(0, Math.round(wrapRight - scrollbarLeft)) + 6 + 'px';
}

/**
 * Adds a small "Select / Copy" toolbar to every code block (<pre> with a
 * <code>) inside `container`. Idempotent — safe to call again on redraw. Each
 * <pre> is wrapped in a positioned element so the toolbar stays at the top-right
 * even when the code scrolls inside a capped-height block.
 */
export default function addCodeToolbar(container) {
  if (!container) return;

  container.querySelectorAll('pre').forEach((pre) => {
    const code = pre.querySelector('code');
    if (!code || pre.dataset.apCodeToolbar) return;
    pre.dataset.apCodeToolbar = '1';

    const wrap = document.createElement('div');
    wrap.className = 'AdvancedPages-codeWrap';
    pre.parentNode.insertBefore(wrap, pre);
    wrap.appendChild(pre);

    const selectLabel = extractText(app.translator.trans('tryhackx-advanced-pages.forum.code.select'));
    const copyLabel = extractText(app.translator.trans('tryhackx-advanced-pages.forum.code.copy'));

    const bar = document.createElement('div');
    bar.className = 'AdvancedPages-codeToolbar';

    const selectBtn = document.createElement('button');
    selectBtn.type = 'button';
    selectBtn.className = 'AdvancedPages-codeBtn';
    selectBtn.textContent = selectLabel;
    selectBtn.addEventListener('click', () => selectCode(code));

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'AdvancedPages-codeBtn';
    copyBtn.textContent = copyLabel;
    copyBtn.addEventListener('click', () => copyCode(code, copyBtn, copyLabel));

    bar.appendChild(selectBtn);
    bar.appendChild(copyBtn);
    wrap.appendChild(bar);

    positionToolbar(wrap, pre, code, bar);
    // Re-position when the block (or its scrollbar) changes size — late
    // highlighting, font load, or a viewport resize crossing the vh cap.
    if (window.ResizeObserver) {
      const ro = new ResizeObserver(() => positionToolbar(wrap, pre, code, bar));
      ro.observe(pre);
      ro.observe(code);
    }
  });
}
