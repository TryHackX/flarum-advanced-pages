import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import ItemList from 'flarum/common/utils/ItemList';
import Form from 'flarum/common/components/Form';
import { marked } from 'marked';
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import php from 'highlight.js/lib/languages/php';
import xml from 'highlight.js/lib/languages/xml';
import css from 'highlight.js/lib/languages/css';
import json from 'highlight.js/lib/languages/json';
import bash from 'highlight.js/lib/languages/bash';
import sql from 'highlight.js/lib/languages/sql';
import python from 'highlight.js/lib/languages/python';
import mdLang from 'highlight.js/lib/languages/markdown';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('js', javascript);
hljs.registerLanguage('php', php);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('html', xml);
hljs.registerLanguage('css', css);
hljs.registerLanguage('json', json);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('python', python);
hljs.registerLanguage('markdown', mdLang);

import CodeEditor from './CodeEditor';
import EditorToolbar from './EditorToolbar';
import ConfirmModal from './ConfirmModal';

marked.setOptions({
  highlight(code, lang) {
    if (lang && hljs.getLanguage(lang)) {
      return hljs.highlight(code, { language: lang }).value;
    }
    return hljs.highlightAuto(code).value;
  },
});

function slugify(text) {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export default class EditPageModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);

    this.page = this.attrs.model || app.store.createRecord('advanced-pages');

    this.pageTitle = Stream(this.page.title() || '');
    this.pageSlug = Stream(this.page.slug() || '');
    this.pageContent = Stream(this.page.content() || '');
    this.pageContentType = Stream(this.page.contentType() || 'html');
    this.pageIsPublished = Stream(this.page.isPublished() !== undefined ? this.page.isPublished() : false);
    this.pageIsHidden = Stream(this.page.isHidden() !== undefined ? this.page.isHidden() : false);
    this.pageIsRestricted = Stream(this.page.isRestricted() !== undefined ? this.page.isRestricted() : false);
    this.pageMetaDescription = Stream(this.page.metaDescription() || '');
    this.pageVisibleGroups = Stream(this.page.visibleGroups() || []);
    this.pageNewlineMode = Stream(this.page.newlineMode() || 'flarum');

    this.showPreview = false;
    this.saved = false;

    this.initialData = JSON.stringify({
      title: this.pageTitle(),
      content: this.pageContent(),
      slug: this.pageSlug(),
      contentType: this.pageContentType(),
      isPublished: this.pageIsPublished(),
      isHidden: this.pageIsHidden(),
      isRestricted: this.pageIsRestricted(),
      metaDescription: this.pageMetaDescription(),
      newlineMode: this.pageNewlineMode(),
    });
  }

  hasUnsavedChanges() {
    return JSON.stringify({
      title: this.pageTitle(),
      content: this.pageContent(),
      slug: this.pageSlug(),
      contentType: this.pageContentType(),
      isPublished: this.pageIsPublished(),
      isHidden: this.pageIsHidden(),
      isRestricted: this.pageIsRestricted(),
      metaDescription: this.pageMetaDescription(),
      newlineMode: this.pageNewlineMode(),
    }) !== this.initialData;
  }

  hide() {
    if (!this.saved && this.hasUnsavedChanges()) {
      this.showConfirmOverlay(
        app.translator.trans('tryhackx-advanced-pages.admin.edit_page.unsaved_title'),
        app.translator.trans('tryhackx-advanced-pages.admin.edit_page.unsaved_message'),
        app.translator.trans('tryhackx-advanced-pages.admin.edit_page.discard_button'),
        app.translator.trans('tryhackx-advanced-pages.admin.edit_page.keep_editing_button'),
        () => {
          this.saved = true;
          super.hide();
        }
      );
      return;
    }
    super.hide();
  }

  showConfirmOverlay(title, message, confirmText, cancelText, onconfirm) {
    if (this._confirmEl) return;
    this._confirmEl = document.createElement('div');
    document.body.appendChild(this._confirmEl);

    const close = () => {
      if (this._confirmEl) {
        m.render(this._confirmEl, null);
        this._confirmEl.remove();
        this._confirmEl = null;
      }
    };

    m.render(this._confirmEl, m('.AdvancedPages-confirmOverlay', {
      onclick: (e) => { if (e.target === e.currentTarget) close(); },
    }, [
      m('.AdvancedPages-confirmBox', [
        m('h3', title),
        m('p', message),
        m('.AdvancedPages-confirmActions', [
          m('button.Button', { onclick: close }, cancelText),
          m('button.Button.Button--danger', {
            onclick: () => { close(); onconfirm(); },
          }, confirmText),
        ]),
      ]),
    ]));
  }

  static isDismissibleViaEscKey = false;
  static isDismissibleViaBackdropClick = false;

  oncreate(vnode) {
    super.oncreate(vnode);

    this._escHandler = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        if (this._confirmEl) return;
        this.hide();
      }
    };
    document.addEventListener('keydown', this._escHandler);

    this._backdropHandler = (e) => {
      if (this._confirmEl) return;
      const modalContent = document.querySelector('.EditPageModal .Modal-content');
      if (modalContent && !modalContent.contains(e.target)) {
        this.hide();
      }
    };
    setTimeout(() => {
      document.addEventListener('mousedown', this._backdropHandler);
    }, 100);
  }

  onremove(vnode) {
    super.onremove(vnode);
    if (this._escHandler) document.removeEventListener('keydown', this._escHandler);
    if (this._backdropHandler) document.removeEventListener('mousedown', this._backdropHandler);
    if (this._confirmEl) {
      this._confirmEl.remove();
      this._confirmEl = null;
    }
  }

  className() {
    return 'EditPageModal Modal--large';
  }

  title() {
    return this.page.exists
      ? app.translator.trans('tryhackx-advanced-pages.admin.edit_page.edit_title')
      : app.translator.trans('tryhackx-advanced-pages.admin.edit_page.create_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <Form>{this.fields().toArray()}</Form>
      </div>
    );
  }

  fields() {
    const items = new ItemList();
    const contentType = this.pageContentType();

    items.add(
      'title',
      <div className="Form-group">
        <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.title_label')}</label>
        <input
          className="FormControl"
          value={this.pageTitle()}
          oninput={(e) => {
            this.pageTitle(e.target.value);
            if (!this.page.exists) {
              this.pageSlug(slugify(e.target.value));
            }
          }}
        />
      </div>,
      100
    );

    items.add(
      'slug',
      <div className="Form-group">
        <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.slug_label')}</label>
        <input
          className="FormControl"
          value={this.pageSlug()}
          oninput={(e) => this.pageSlug(e.target.value)}
        />
      </div>,
      90
    );

    items.add(
      'contentType',
      <div className="Form-group">
        <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.content_type_label')}</label>
        <select
          className="FormControl"
          value={contentType}
          onchange={(e) => {
            this.pageContentType(e.target.value);
            this.showPreview = false;
          }}
        >
          <option value="html">HTML</option>
          <option value="bbcode">BBCode</option>
          <option value="markdown">Markdown</option>
          <option value="text">{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.type_text')}</option>
          <option value="php">PHP</option>
        </select>
      </div>,
      80
    );

    if (contentType === 'php') {
      items.add(
        'phpWarning',
        <div className="Form-group">
          <div className="Alert Alert--warning">
            {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.php_warning')}
          </div>
        </div>,
        75
      );
    }

    if (contentType === 'bbcode') {
      items.add(
        'newlineMode',
        <div className="Form-group">
          <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.newline_mode_label')}</label>
          <select
            className="FormControl"
            value={this.pageNewlineMode()}
            onchange={(e) => this.pageNewlineMode(e.target.value)}
          >
            <option value="flarum">{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.newline_flarum')}</option>
            <option value="preserve">{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.newline_preserve')}</option>
          </select>
        </div>,
        76
      );
    }

    items.add('pageContent', this.buildEditorField(contentType), 70);

    items.add(
      'visibility',
      <div className="Form-group">
        <div className="AdvancedPages-visibilityGroup">
          <label className="checkbox">
            <input type="checkbox" checked={this.pageIsPublished()} onchange={(e) => this.pageIsPublished(e.target.checked)} />
            {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.is_published_label')}
          </label>
          <label className="checkbox">
            <input type="checkbox" checked={this.pageIsHidden()} onchange={(e) => this.pageIsHidden(e.target.checked)} />
            {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.is_hidden_label')}
          </label>
          <label className="checkbox">
            <input type="checkbox" checked={this.pageIsRestricted()} onchange={(e) => this.pageIsRestricted(e.target.checked)} />
            {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.is_restricted_label')}
          </label>
        </div>
      </div>,
      60
    );

    items.add(
      'visibleGroups',
      <div className="Form-group">
        <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.visible_groups_label')}</label>
        <div className="AdvancedPages-groupCheckboxes">
          {app.store
            .all('groups')
            .filter((group) => group.id() !== '1')
            .map((group) => (
              <label className="checkbox" key={group.id()}>
                <input
                  type="checkbox"
                  checked={this.pageVisibleGroups().length === 0 || this.pageVisibleGroups().includes(Number(group.id()))}
                  onchange={(e) => {
                    let groups = [...this.pageVisibleGroups()];
                    const groupId = Number(group.id());

                    if (groups.length === 0) {
                      const allGroupIds = app.store
                        .all('groups')
                        .filter((g) => g.id() !== '1')
                        .map((g) => Number(g.id()));
                      groups = e.target.checked ? allGroupIds : allGroupIds.filter((id) => id !== groupId);
                    } else {
                      if (e.target.checked) {
                        groups.push(groupId);
                      } else {
                        groups = groups.filter((id) => id !== groupId);
                      }
                    }

                    const allNonAdmin = app.store
                      .all('groups')
                      .filter((g) => g.id() !== '1')
                      .map((g) => Number(g.id()));
                    if (allNonAdmin.every((id) => groups.includes(id))) {
                      groups = [];
                    }

                    this.pageVisibleGroups(groups);
                  }}
                />
                {group.nameSingular()}
              </label>
            ))}
        </div>
        <p className="helpText">{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.visible_groups_help')}</p>
      </div>,
      35
    );

    items.add(
      'metaDescription',
      <div className="Form-group">
        <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.meta_description_label')}</label>
        <textarea
          className="FormControl"
          rows="2"
          value={this.pageMetaDescription()}
          oninput={(e) => this.pageMetaDescription(e.target.value)}
        />
      </div>,
      30
    );

    items.add(
      'submit',
      <div className="Form-group Form-controls">
        <Button type="submit" className="Button Button--primary" loading={this.loading}>
          {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.submit_button')}
        </Button>
        {this.page.exists && (
          <button type="button" className="Button Button--danger" onclick={this.deletePage.bind(this)}>
            {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.delete_button')}
          </button>
        )}
      </div>,
      -10
    );

    return items;
  }

  buildEditorField(contentType) {
    const useCodeEditor = contentType === 'html' || contentType === 'php';
    const useToolbar = contentType === 'bbcode' || contentType === 'markdown';
    const canPreview = contentType !== 'php';

    return (
      <div className="Form-group">
        <label>{app.translator.trans('tryhackx-advanced-pages.admin.edit_page.content_label')}</label>

        {/* BBCode/Markdown: toolbar with formatting buttons + preview toggle */}
        {useToolbar && (
          <EditorToolbar
            contentType={contentType}
            textareaRef={this.contentTextarea}
            showPreview={this.showPreview}
            onTogglePreview={(show) => { this.showPreview = show; }}
          />
        )}

        {/* HTML/Text: simple preview toggle bar — no formatting buttons, just Raw/Preview */}
        {!useToolbar && canPreview && (
          <div className="AdvancedPages-editorToolbar">
            <div className="AdvancedPages-previewToggle">
              <Button
                className={'Button Button--sm' + (this.showPreview ? '' : ' active')}
                onclick={() => { this.showPreview = false; }}
              >
                {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.raw')}
              </Button>
              <Button
                className={'Button Button--sm' + (this.showPreview ? ' active' : '')}
                onclick={() => { this.showPreview = true; }}
              >
                {app.translator.trans('tryhackx-advanced-pages.admin.edit_page.preview')}
              </Button>
            </div>
          </div>
        )}

        {/* Editor area */}
        {!this.showPreview ? (
          useCodeEditor ? (
            <CodeEditor
              language={contentType}
              value={this.pageContent()}
              onchange={(value) => this.pageContent(value)}
            />
          ) : (
            <textarea
              className="FormControl AdvancedPages-contentEditor"
              rows="15"
              value={this.pageContent()}
              oninput={(e) => this.pageContent(e.target.value)}
              oncreate={(vnode) => { this.contentTextarea = vnode.dom; }}
              onupdate={(vnode) => { this.contentTextarea = vnode.dom; }}
            />
          )
        ) : (
          <div className="AdvancedPages-markdownPreview"
            oncreate={(vnode) => this.highlightPreview(vnode.dom)}
            onupdate={(vnode) => this.highlightPreview(vnode.dom)}
          >
            {m.trust(this.renderPreview(contentType))}
          </div>
        )}
      </div>
    );
  }

  highlightPreview(dom) {
    dom.querySelectorAll('pre code').forEach((block) => {
      block.removeAttribute('data-highlighted');
      hljs.highlightElement(block);
    });
  }

  renderPreview(contentType) {
    const content = this.pageContent() || '';

    switch (contentType) {
      case 'html':
        return content;
      case 'markdown':
        try {
          return marked(content, { breaks: true });
        } catch (e) {
          return '<p>' + content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>';
        }
      case 'bbcode':
        return this.renderBbcode(content);
      case 'text':
        return '<div style="white-space:pre-wrap">' + content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
      default:
        return content;
    }
  }

  renderBbcode(text) {
    let html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const codeBlocks = [];
    html = html.replace(/\[code\]([\s\S]*?)\[\/code\]/gi, (_, code) => {
      code = code.replace(/^\n/, '').replace(/\n$/, '');
      codeBlocks.push(code);
      return '\x00CODE' + (codeBlocks.length - 1) + '\x00';
    });

    const replacements = [
      [/\[b\]([\s\S]*?)\[\/b\]/gi, '<strong>$1</strong>'],
      [/\[i\]([\s\S]*?)\[\/i\]/gi, '<em>$1</em>'],
      [/\[u\]([\s\S]*?)\[\/u\]/gi, '<u>$1</u>'],
      [/\[s\]([\s\S]*?)\[\/s\]/gi, '<del>$1</del>'],
      [/\[del\]([\s\S]*?)\[\/del\]/gi, '<del>$1</del>'],
      [/\[center\]([\s\S]*?)\[\/center\]/gi, '<div style="text-align:center">$1</div>'],
      [/\[url=(.*?)\]([\s\S]*?)\[\/url\]/gi, '<a href="$1">$2</a>'],
      [/\[url\]([\s\S]*?)\[\/url\]/gi, '<a href="$1">$1</a>'],
      [/\[email\]([\s\S]*?)\[\/email\]/gi, '<a href="mailto:$1">$1</a>'],
      [/\[img\]([\s\S]*?)\[\/img\]/gi, '<img src="$1" style="max-width:100%">'],
      [/\[magnet\]([\s\S]*?)\[\/magnet\]/gi, '<a href="$1">🧲 Magnet Link</a>'],
      [/\[color=(.*?)\]([\s\S]*?)\[\/color\]/gi, '<span style="color:$1">$2</span>'],
      [/\[size=(\d+)\]([\s\S]*?)\[\/size\]/gi, '<span style="font-size:$1px">$2</span>'],
      [/\[quote\]([\s\S]*?)\[\/quote\]/gi, '<blockquote>$1</blockquote>'],
      [/\[quote=(.*?)\]([\s\S]*?)\[\/quote\]/gi, '<blockquote><strong>$1:</strong><br>$2</blockquote>'],
      [/\[spoiler=(.*?)\]([\s\S]*?)\[\/spoiler\]/gi, '<details class="AdvancedPages-spoiler"><summary><span class="AdvancedPages-spoilerIcon"><i class="fas fa-eye"></i></span> <span class="AdvancedPages-spoilerTitle">Spoiler: $1</span></summary><div class="AdvancedPages-spoilerContent">$2</div></details>'],
      [/\[spoiler\]([\s\S]*?)\[\/spoiler\]/gi, '<details class="AdvancedPages-spoiler"><summary><span class="AdvancedPages-spoilerIcon"><i class="fas fa-eye"></i></span> <span class="AdvancedPages-spoilerTitle">Spoiler</span></summary><div class="AdvancedPages-spoilerContent">$1</div></details>'],
      [/\[list=1\]([\s\S]*?)\[\/list\]/gi, (_, items) => '<ol>' + items.replace(/\[\*\]\s*(.*?)(?=\[\*\]|$)/gs, '<li>$1</li>') + '</ol>'],
      [/\[list\]([\s\S]*?)\[\/list\]/gi, (_, items) => '<ul>' + items.replace(/\[\*\]\s*(.*?)(?=\[\*\]|$)/gs, '<li>$1</li>') + '</ul>'],
      [/\[table\]([\s\S]*?)\[\/table\]/gi, '<table style="border-collapse:collapse;width:100%">$1</table>'],
      [/\[tr\]([\s\S]*?)\[\/tr\]/gi, '<tr>$1</tr>'],
      [/\[th\]([\s\S]*?)\[\/th\]/gi, '<th style="border:1px solid var(--control-bg);padding:8px;font-weight:600;background:var(--control-bg)">$1</th>'],
      [/\[td\]([\s\S]*?)\[\/td\]/gi, '<td style="border:1px solid var(--control-bg);padding:8px">$1</td>'],
    ];
    replacements.forEach(([regex, replacement]) => { html = html.replace(regex, replacement); });

    html = html.replace(/<table[^>]*>[\s\S]*?<\/table>/gi, (match) => match.replace(/\n/g, ''));
    html = html.replace(/<[uo]l>[\s\S]*?<\/[uo]l>/gi, (match) => match.replace(/\n/g, ''));
    html = html.replace(/(<(?:blockquote|details|summary|div[^>]*)>)\n/gi, '$1');
    html = html.replace(/\n(<\/(?:blockquote|details|summary|div)>)/gi, '$1');

    const newlineMode = this.pageNewlineMode ? this.pageNewlineMode() : 'flarum';
    if (newlineMode === 'preserve') {
      html = html.replace(/\n/g, '<br>');
    } else {
      html = html.replace(/\n{2,}/g, '<br>');
      html = html.replace(/\n/g, ' ');
    }

    codeBlocks.forEach((code, i) => {
      html = html.replace('\x00CODE' + i + '\x00', '<pre><code>' + code + '</code></pre>');
    });

    html = html.replace(/(<\/(?:pre|blockquote|details|ul|ol|table|div)>)<br>/gi, '$1');

    return html;
  }

  submitData() {
    return {
      title: this.pageTitle(),
      slug: this.pageSlug(),
      content: this.pageContent(),
      contentType: this.pageContentType(),
      isPublished: this.pageIsPublished(),
      isHidden: this.pageIsHidden(),
      isRestricted: this.pageIsRestricted(),
      metaDescription: this.pageMetaDescription(),
      visibleGroups: this.pageVisibleGroups().length > 0 ? this.pageVisibleGroups() : null,
      newlineMode: this.pageNewlineMode(),
    };
  }

  onsubmit(e) {
    e.preventDefault();
    this.loading = true;

    this.page.save(this.submitData()).then(
      () => {
        this.saved = true;
        this.hide();
        if (this.attrs.oncreate) this.attrs.oncreate();
        if (this.attrs.onhide) this.attrs.onhide();
        m.redraw();
      },
      (response) => {
        this.loading = false;
        this.handleErrors(response);
        m.redraw();
      }
    );
  }

  deletePage() {
    app.modal.show(ConfirmModal, {
      title: app.translator.trans('tryhackx-advanced-pages.admin.edit_page.delete_title'),
      message: app.translator.trans('tryhackx-advanced-pages.admin.edit_page.delete_confirmation'),
      confirmText: app.translator.trans('tryhackx-advanced-pages.admin.edit_page.delete_button'),
      cancelText: app.translator.trans('tryhackx-advanced-pages.admin.edit_page.cancel_button'),
      onconfirm: () => {
        this.page.delete().then(() => {
          if (this.attrs.oncreate) this.attrs.oncreate();
          if (this.attrs.onhide) this.attrs.onhide();
          m.redraw();
        });
      },
    });
  }

  handleErrors(response) {
    this.alertAttrs = response.alert;
  }
}
