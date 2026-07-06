import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';

// Add admin-pinned Advanced Pages to the index-sidebar navigation — the same
// "Dropdown-menu" list where core's "All Discussions" and extension links like
// Members/Badges live. The pinned set rides along on the forum payload
// (`advancedPagesPinned`), already scoped to what the viewer may see and ordered
// like the admin page list. Every link points at the page's own /p/{slug} route
// (even redirect pages), so a click always goes through the counted view path;
// a redirect page then forwards from there. The attribute is read at render time
// (inside the navItems hook), never at init — app.forum isn't populated when
// initializers run.
export default function pinnedNav() {
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    const pinned = app.forum.attribute('advancedPagesPinned');
    if (!Array.isArray(pinned) || pinned.length === 0) return;

    // Descend from 90 (just under core's "All Discussions" at 100) so the pinned
    // links keep the page-list order and sit at the top of the menu.
    pinned.forEach((page, index) => {
      if (!page || !page.slug) return;

      items.add(
        'advancedPage-' + page.slug,
        // `external` makes this a plain full-page-load link rather than a
        // client-side (SPA) route. That matters because a pinned page is often a
        // redirect: a full load hits /p/{slug} on the server, which redirects
        // straight away (a 302, or the countdown page) — no PageView "Loading…"
        // step, and it works for PHP header()-based pages too. It mirrors how the
        // admin page-list "open" link already behaves.
        <LinkButton href={'/p/' + page.slug} icon={page.icon || 'far fa-file-alt'} external={true}>
          {page.title}
        </LinkButton>,
        90 - index
      );
    });
  });
}
