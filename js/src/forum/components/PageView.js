import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import addCodeToolbar from '../addCodeToolbar';
import hljs from '../../common/hljs';

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
      if (this.maybeRedirect()) return;
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
        if (this.maybeRedirect()) return;
        this.updateTitle();
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        this.notFound = true;
        m.redraw();
      });
  }

  // Immediate "redirect" pages forward right away (a full page load is already
  // 302'd server-side; this covers in-app SPA navigation). Landing pages instead
  // render and run a visible countdown — see maybeStartRedirectCountdown, called
  // once the DOM exists. Returns true when it forwarded synchronously (so the
  // caller can skip rendering).
  maybeRedirect() {
    if (!this.page || this.page.contentType() !== 'redirect') return false;
    if (this.page.redirectImmediate() === false) return false; // landing → countdown after render

    const target = this.page.redirectUrl();
    if (!target) return false;

    // redirectUrl is validated server-side (http(s):// or root-relative "/…"),
    // so it is safe to assign. replace() keeps the redirect out of history.
    window.location.replace(target);
    return true;
  }

  // For a landing-mode redirect page, tick the "Redirecting in N…" countdown
  // rendered by RedirectRenderer down to zero, then forward. Called from
  // oncreate/onupdate (needs the DOM); runs at most once per loaded page.
  maybeStartRedirectCountdown(dom) {
    if (this._redirectInterval || !dom) return;
    if (!this.page || this.page.contentType() !== 'redirect') return;
    if (this.page.redirectImmediate() !== false) return;

    const target = this.page.redirectUrl();
    if (!target) return;

    const container = dom.querySelector('.AdvancedPages-redirect[data-redirect-seconds]');
    let seconds = container ? parseInt(container.getAttribute('data-redirect-seconds'), 10) : 5;
    if (!(seconds > 0)) seconds = 5;

    this._redirectInterval = setInterval(() => {
      seconds -= 1;
      const span = dom.querySelector('.AdvancedPages-redirectSeconds');
      if (span) span.textContent = String(Math.max(seconds, 0));
      if (seconds <= 0) {
        clearInterval(this._redirectInterval);
        this._redirectInterval = null;
        window.location.replace(target);
      }
    }, 1000);
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
    addCodeToolbar(vnode.dom);
    this.maybeActivateScripts(vnode.dom);
    this.maybeStartRedirectCountdown(vnode.dom);
  }

  onupdate(vnode) {
    super.onupdate(vnode);
    this.highlightCode(vnode.dom);
    addCodeToolbar(vnode.dom);
    this.maybeActivateScripts(vnode.dom);
    this.maybeStartRedirectCountdown(vnode.dom);
  }

  onremove(vnode) {
    super.onremove(vnode);
    // Cancel a pending landing-mode redirect if the user navigates away first.
    if (this._redirectInterval) {
      clearInterval(this._redirectInterval);
      this._redirectInterval = null;
    }
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
