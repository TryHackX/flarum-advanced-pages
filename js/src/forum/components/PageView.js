import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
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

    // store.find() addresses resources as /advanced-pages/{id} and so can't carry
    // a nested (slash-containing) slug. Resolve through the by-slug endpoint and
    // push the returned document into the store ourselves. Slugs only contain
    // [a-z0-9-/], so the path needs no encoding (which would mangle the slashes).
    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/advanced-pages-by-slug/' + slug,
      })
      .then((payload) => {
        this.page = app.store.pushPayload(payload);
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
    this.maybeActivateScripts(vnode.dom);
  }

  onupdate(vnode) {
    super.onupdate(vnode);
    this.highlightCode(vnode.dom);
    this.maybeActivateScripts(vnode.dom);
  }

  // Runs activateScripts at most once per loaded page, whether the page arrives
  // via oncreate (server-preloaded) or a later onupdate (client-side fetch).
  // Script execution is opt-in per page (allowScripts) — see activateScripts.
  maybeActivateScripts(dom) {
    if (this._scriptsActivated || !this.page || this.loading) return;
    if (!this.page.allowScripts()) return;
    this._scriptsActivated = true;
    this.activateScripts(dom);
  }

  highlightCode(dom) {
    if (!dom) return;
    dom.querySelectorAll('pre code, code[class*="language-"]').forEach((block) => {
      if (!block.dataset.highlighted) {
        hljs.highlightElement(block);
      }
    });
  }

  // Executes <script> tags embedded in page content. Only ever called for pages
  // with allowScripts() === true (gated in maybeActivateScripts), which in turn
  // can only be set on html/php pages whose creation required the sensitive
  // permission. Browsers do not run scripts inserted via innerHTML, so we clone
  // each tag into a fresh element to opt back into execution.
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
            {this.treeStyle()}
            {this.breadcrumbs()}
            <div className="AdvancedPages-content">
              {m.trust(this.page.contentHtml())}
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Build breadcrumbs from the parent chain: each ancestor links to its own
  // page, the last crumb is the current page's title. Only shown when the page
  // has a parent. The ancestor data comes from the API (resolved server-side).
  breadcrumbs() {
    if (!this.page) return null;

    const ancestors = this.page.ancestors();
    if (!ancestors.length) return null;

    const items = [];

    ancestors.forEach((ancestor, index) => {
      if (index > 0) {
        items.push(<span className="AdvancedPages-breadcrumbSep">/</span>);
      }
      items.push(
        <Link className="AdvancedPages-breadcrumbLink" href={'/p/' + ancestor.slug}>
          {ancestor.title}
        </Link>
      );
    });

    items.push(<span className="AdvancedPages-breadcrumbSep">/</span>);
    items.push(<span className="AdvancedPages-breadcrumbCurrent">{this.page.title()}</span>);

    return <nav className="AdvancedPages-breadcrumbs">{items}</nav>;
  }

  // Per-tree breadcrumb CSS (set on the tree's root page). Rendered as a <style>
  // element whose text content is the author's CSS — set as a text node, so a
  // stray "</style>" is inert text, not a tag break. Lets a tree restyle or hide
  // (display:none) its breadcrumbs.
  treeStyle() {
    const css = this.page && this.page.treeBreadcrumbsCss();
    if (!css) return null;

    return <style className="AdvancedPages-treeStyle">{css}</style>;
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
