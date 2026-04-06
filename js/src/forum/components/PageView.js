import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import php from 'highlight.js/lib/languages/php';
import xml from 'highlight.js/lib/languages/xml';
import css from 'highlight.js/lib/languages/css';
import json from 'highlight.js/lib/languages/json';
import bash from 'highlight.js/lib/languages/bash';
import sql from 'highlight.js/lib/languages/sql';
import python from 'highlight.js/lib/languages/python';
import markdown from 'highlight.js/lib/languages/markdown';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('js', javascript);
hljs.registerLanguage('php', php);
hljs.registerLanguage('html', xml);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('css', css);
hljs.registerLanguage('json', json);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('shell', bash);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('python', python);
hljs.registerLanguage('markdown', markdown);

export default class PageView extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.page = null;
    this.notFound = false;

    this.bodyClass = 'App--advancedPage';

    this.loadPage();
  }

  loadPage() {
    const slug = m.route.param('slug');

    const preloaded = app.preloadedApiDocument();
    if (preloaded) {
      this.page = preloaded;
      this.loading = false;
      this.updateTitle();
      return;
    }

    app.store
      .find('advanced-pages', slug)
      .then((page) => {
        this.page = page;
        this.loading = false;
        this.updateTitle();
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        this.notFound = true;
        m.redraw();
      });
  }

  updateTitle() {
    if (this.page) {
      app.setTitle(this.page.title());

      if (this.page.metaDescription()) {
        const meta = document.querySelector('meta[name="description"]');
        if (meta) {
          meta.setAttribute('content', this.page.metaDescription());
        }
      }
    }
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    this.highlightCode(vnode.dom);
    if (this.page && !this.loading) {
      this.activateScripts(vnode.dom);
    }
  }

  onupdate(vnode) {
    super.onupdate(vnode);
    this.highlightCode(vnode.dom);
    if (this.page && !this.loading && !this._scriptsActivated) {
      this._scriptsActivated = true;
      this.activateScripts(vnode.dom);
    }
  }

  highlightCode(dom) {
    if (!dom) return;
    dom.querySelectorAll('pre code, code[class*="language-"]').forEach((block) => {
      if (!block.dataset.highlighted) {
        hljs.highlightElement(block);
      }
    });
  }

  activateScripts(dom) {
    if (!dom) return;
    const container = dom.querySelector('.AdvancedPages-content');
    if (!container) return;

    const scripts = Array.from(container.querySelectorAll('script'));
    if (!scripts.length) return;

    const externalScripts = scripts.filter((s) => s.src);
    const inlineScripts = scripts.filter((s) => !s.src);

    const loadExternal = externalScripts.map(
      (oldScript) =>
        new Promise((resolve) => {
          const newScript = document.createElement('script');
          Array.from(oldScript.attributes).forEach((attr) => {
            newScript.setAttribute(attr.name, attr.value);
          });
          newScript.onload = resolve;
          newScript.onerror = resolve;
          oldScript.parentNode.replaceChild(newScript, oldScript);
        })
    );

    Promise.all(loadExternal).then(() => {
      inlineScripts.forEach((oldScript) => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach((attr) => {
          newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
      });

      if (document.readyState !== 'loading') {
        document.dispatchEvent(new Event('DOMContentLoaded'));
      }
    });
  }

  view() {
    if (this.loading) {
      return (
        <div className="Page AdvancedPages-page">
          {this.hero()}
          <div className="Page-main">
            <LoadingIndicator />
          </div>
        </div>
      );
    }

    if (this.notFound || !this.page) {
      return (
        <div className="Page AdvancedPages-page">
          {this.hero()}
          <div className="Page-main">
            <div className="container">
              <div className="AdvancedPages-notFound">
                <h2>{app.translator.trans('tryhackx-advanced-pages.forum.page.not_found_title')}</h2>
                <p>{app.translator.trans('tryhackx-advanced-pages.forum.page.not_found_message')}</p>
              </div>
            </div>
          </div>
        </div>
      );
    }

    return (
      <div className="Page AdvancedPages-page">
        {this.hero()}
        <div className="Page-main">
          <div className="container">
            <div className="AdvancedPages-content">
              {m.trust(this.page.contentHtml())}
            </div>
          </div>
        </div>
      </div>
    );
  }

  hero() {
    return (
      <header className="Hero AdvancedPages-hero">
        <div className="container">
          <div className="containerNarrow">
            <h1 className="Hero-title">
              {this.page ? this.page.title() : app.translator.trans('tryhackx-advanced-pages.forum.page.loading')}
            </h1>
          </div>
        </div>
      </header>
    );
  }
}
