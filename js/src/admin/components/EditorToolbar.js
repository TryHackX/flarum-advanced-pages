import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';

/**
 * Inserts text around the current selection in a textarea.
 */
function insertAtCursor(textarea, before, after = '', placeholder = '') {
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = textarea.value;
  const selected = text.substring(start, end);

  let replacement, selectStart, selectEnd;

  if (selected) {
    replacement = before + selected + after;
    selectStart = start + before.length;
    selectEnd = start + before.length + selected.length;
  } else {
    replacement = before + placeholder + after;
    selectStart = start + before.length;
    selectEnd = start + before.length + placeholder.length;
  }

  textarea.focus();
  const success = document.execCommand('insertText', false, replacement);
  if (!success) {
    textarea.value = text.substring(0, start) + replacement + text.substring(end);
  }
  textarea.selectionStart = selectStart;
  textarea.selectionEnd = selectEnd;
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function isExtEnabled(id) {
  try {
    const enabled = JSON.parse(app.data.settings.extensions_enabled || '[]');
    return enabled.includes(id);
  } catch (e) {
    return false;
  }
}

const EMOJI_LIST = [
  '😀', '😃', '😄', '😁', '😂', '🤣', '😊', '😇', '🙂', '😉', '😍', '🥰',
  '😘', '😜', '🤪', '😎', '🤓', '🧐', '🤔', '🤨', '😐', '😑', '😶', '🙄',
  '😏', '😣', '😥', '😢', '😭', '😤', '😡', '🤬', '😱', '😰', '😓', '🤗',
  '👍', '👎', '👏', '🙏', '🤝', '💪', '✌️', '🤞', '👌', '🤙', '👋', '✋',
  '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '💔', '❣️', '💕', '💯', '💢',
  '⭐', '🌟', '✨', '🔥', '💡', '🎉', '🎊', '🏆', '🥇', '🎯', '🚀', '💎',
  '⚡', '☀️', '🌈', '🌍', '🎵', '🎶', '📌', '📎', '🔗', '🔒', '🔑', '🛠️',
  '✅', '❌', '⚠️', '❓', '❗', '💬', '👀', '🔔', '📢', '🏷️', '📝', '📋',
];

export default class EditorToolbar extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.showEmojiPicker = false;
    this._portalEl = null;
    this._closeHandler = (e) => {
      if (this._portalEl && !this._portalEl.contains(e.target)) {
        this.closeEmojiPicker();
        m.redraw();
      }
    };
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    document.addEventListener('click', this._closeHandler);
  }

  onremove(vnode) {
    super.onremove(vnode);
    document.removeEventListener('click', this._closeHandler);
    this.closeEmojiPicker();
  }

  closeEmojiPicker() {
    this.showEmojiPicker = false;
    if (this._portalEl) {
      document.body.removeChild(this._portalEl);
      this._portalEl = null;
    }
  }

  openEmojiPicker(buttonRect) {
    this.showEmojiPicker = true;

    if (!this._portalEl) {
      this._portalEl = document.createElement('div');
      document.body.appendChild(this._portalEl);
    }

    const top = buttonRect.bottom + 4;
    const left = Math.max(8, Math.min(buttonRect.left, window.innerWidth - 350));

    const self = this;
    m.render(this._portalEl, m('.AdvancedPages-emojiPicker', {
      style: { top: top + 'px', left: left + 'px' },
      onclick: (e) => e.stopPropagation(),
    }, EMOJI_LIST.map((emoji) =>
      m('button.AdvancedPages-emojiBtn', {
        type: 'button',
        onclick: () => { self.insertRaw(emoji); self.closeEmojiPicker(); m.redraw(); },
      }, emoji)
    )));
  }

  view() {
    const type = this.attrs.contentType;
    if (type === 'bbcode') return this.bbcodeToolbar();
    if (type === 'markdown') return this.markdownToolbar();
    return null;
  }

  btn(icon, title, before, after, placeholder) {
    return (
      <Button
        className="Button Button--icon Button--link"
        icon={icon}
        title={title}
        onclick={() => this.insert(before, after, placeholder)}
      />
    );
  }

  emojiButton() {
    const hasEmoji = isExtEnabled('flarum-emoji');
    if (!hasEmoji) return null;

    return (
      <div className="AdvancedPages-emojiWrapper">
        <Button
          className="Button Button--icon Button--link"
          icon="far fa-smile"
          title="Emoji"
          onclick={(e) => {
            e.stopPropagation();
            if (this.showEmojiPicker) {
              this.closeEmojiPicker();
            } else {
              this.openEmojiPicker(e.currentTarget.getBoundingClientRect());
            }
          }}
        />
      </div>
    );
  }

  group(items) {
    return <span className="AdvancedPages-toolbarGroup">{items}</span>;
  }

  bbcodeToolbar() {
    const hasBbcode = isExtEnabled('flarum-bbcode');
    const hasMagnet = isExtEnabled('tryhackx-magnet-link');

    const groups = [];

    groups.push(this.group([
      this.btn('fas fa-heading', 'Heading', '[size=20]', '[/size]', 'heading text'),
      this.btn('fas fa-bold', 'Bold', '[b]', '[/b]', 'bold text'),
      this.btn('fas fa-italic', 'Italic', '[i]', '[/i]', 'italic text'),
      this.btn('fas fa-underline', 'Underline', '[u]', '[/u]', 'underlined text'),
      this.btn('fas fa-strikethrough', 'Strikethrough', '[s]', '[/s]', 'strikethrough text'),
    ]));

    const blockBtns = [
      this.btn('fas fa-quote-left', 'Quote', '[quote]', '[/quote]', 'quoted text'),
    ];
    if (hasBbcode) {
      blockBtns.push(this.btn('fas fa-exclamation-triangle', 'Spoiler', '[spoiler]', '[/spoiler]', 'hidden text'));
    }
    blockBtns.push(this.btn('fas fa-code', 'Code', '[code]', '[/code]', 'code here'));
    blockBtns.push(this.btn('fas fa-palette', 'Color', '[color=#e74c3c]', '[/color]', 'colored text'));
    blockBtns.push(this.btn('fas fa-align-center', 'Center', '[center]', '[/center]', 'centered text'));
    groups.push(this.group(blockBtns));

    const linkBtns = [
      this.btn('fas fa-link', 'Link', '[url=https://]', '[/url]', 'link text'),
      this.btn('fas fa-image', 'Image', '[img]', '[/img]', 'https://example.com/image.png'),
      this.btn('fas fa-envelope', 'Email', '[email]', '[/email]', 'user@example.com'),
    ];
    if (hasMagnet) {
      linkBtns.push(this.btn('fas fa-magnet', 'Magnet Link', '[magnet]', '[/magnet]', 'magnet:?xt=urn:btih:...'));
    }
    groups.push(this.group(linkBtns));

    const lastRow = [];
    lastRow.push(this.group([
      this.btn('fas fa-list-ul', 'Unordered List', '[list]\n[*] ', '\n[*] item\n[/list]', 'item'),
      this.btn('fas fa-list-ol', 'Ordered List', '[list=1]\n[*] ', '\n[*] item\n[/list]', 'item'),
      this.btn('fas fa-table', 'Table', '[table]\n[tr]\n[th]Header 1[/th]\n[th]Header 2[/th]\n[/tr]\n[tr]\n[td]', '[/td]\n[td]Cell 2[/td]\n[/tr]\n[/table]', 'Cell 1'),
    ]));
    const emojiBtn = this.emojiButton();
    if (emojiBtn) {
      lastRow.push(this.group([emojiBtn]));
    }
    groups.push(<div className="AdvancedPages-toolbarLastRow">{lastRow}</div>);

    return this.renderToolbar(groups);
  }

  markdownToolbar() {
    const hasMagnet = isExtEnabled('tryhackx-magnet-link');

    const groups = [];

    groups.push(this.group([
      this.btn('fas fa-heading', 'Heading', '## ', '', 'heading'),
      this.btn('fas fa-bold', 'Bold', '**', '**', 'bold text'),
      this.btn('fas fa-italic', 'Italic', '*', '*', 'italic text'),
      this.btn('fas fa-strikethrough', 'Strikethrough', '~~', '~~', 'text'),
      this.btn('fas fa-underline', 'Underline', '<u>', '</u>', 'underlined text'),
    ]));

    groups.push(this.group([
      this.btn('fas fa-quote-left', 'Quote', '> ', '', 'quote'),
      this.btn('fas fa-exclamation-triangle', 'Spoiler', '>!', '!<', 'hidden text'),
      this.btn('fas fa-code', 'Inline Code', '`', '`', 'code'),
      this.btn('fas fa-file-code', 'Code Block', '```\n', '\n```', 'code here'),
    ]));

    const linkBtns = [
      this.btn('fas fa-link', 'Link', '[', '](https://)', 'link text'),
      this.btn('fas fa-image', 'Image', '![', '](https://example.com/image.png)', 'alt text'),
    ];
    if (hasMagnet) {
      linkBtns.push(this.btn('fas fa-magnet', 'Magnet Link', '[magnet](', ')', 'magnet:?xt=urn:btih:...'));
    }
    groups.push(this.group(linkBtns));

    const lastRow = [];
    lastRow.push(this.group([
      this.btn('fas fa-list-ul', 'Unordered List', '- ', '', 'item'),
      this.btn('fas fa-list-ol', 'Ordered List', '1. ', '', 'item'),
      this.btn('fas fa-tasks', 'Task List', '- [ ] ', '', 'task'),
      this.btn('fas fa-table', 'Table', '| Header 1 | Header 2 |\n| --- | --- |\n| ', ' | Cell 2 |', 'Cell 1'),
      this.btn('fas fa-minus', 'Horizontal Rule', '\n---\n', '', ''),
    ]));
    const emojiBtn = this.emojiButton();
    if (emojiBtn) {
      lastRow.push(this.group([emojiBtn]));
    }
    groups.push(<div className="AdvancedPages-toolbarLastRow">{lastRow}</div>);

    return this.renderToolbar(groups);
  }

  renderToolbar(buttons) {
    return (
      <div className="AdvancedPages-editorToolbar">
        <div className={'AdvancedPages-toolbarButtons' + (this.attrs.showPreview ? ' disabled' : '')}>
          {buttons}
        </div>
        {this.attrs.onTogglePreview && (
          <div className="AdvancedPages-previewToggle">
            <Button
              className={'Button Button--sm' + (this.attrs.showPreview ? '' : ' active')}
              onclick={() => this.attrs.onTogglePreview(false)}
            >
              {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.raw')}
            </Button>
            <Button
              className={'Button Button--sm' + (this.attrs.showPreview ? ' active' : '')}
              onclick={() => this.attrs.onTogglePreview(true)}
            >
              {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.preview')}
            </Button>
          </div>
        )}
      </div>
    );
  }

  insert(before, after, placeholder) {
    const textarea = this.attrs.textareaRef;
    if (textarea) insertAtCursor(textarea, before, after, placeholder);
  }

  insertRaw(text) {
    const textarea = this.attrs.textareaRef;
    if (textarea) insertAtCursor(textarea, text, '', '');
  }
}
