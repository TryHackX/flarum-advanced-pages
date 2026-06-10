import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';

import EditPageModal from './EditPageModal';
import SupportModal from './SupportModal';

const PER_PAGE_OPTIONS = { 10: '10', 20: '20', 30: '30', 50: '50', 100: '100' };

export default class AdvancedPagesPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.currentPage = 1;
    this.perPage = parseInt(localStorage.getItem('advancedPages.perPage') || '30', 10);

    this.loadPages();
  }

  loadPages() {
    this.loading = true;
    this.loadPageBatch(0);
  }

  // The API Index endpoint paginates (max 50 per page), so a single find() only
  // ever returned the first page — any pages beyond that were invisible in the
  // admin list. Walk every page so the table sees the full set; store.find()
  // pushes each batch into the store, which is what we render from. The offset
  // cap is a safety stop against an unbounded loop.
  loadPageBatch(offset) {
    const limit = 50;

    app.store
      .find('advanced-pages', { page: { offset, limit } })
      .then((batch) => {
        if (batch.length === limit && offset + limit < 10000) {
          this.loadPageBatch(offset + limit);
        } else {
          this.finishLoading();
        }
      })
      .catch(() => {
        // Never leave the list stuck on the loading spinner if a request fails
        // (e.g. a transient error); show whatever loaded so far.
        this.finishLoading();
      });
  }

  finishLoading() {
    this.loading = false;
    m.redraw();
    // The admin page can mount twice; the immediate redraw may target an
    // already-replaced instance, leaving the visible one stuck on the spinner.
    // A deferred redraw repaints whichever instance is finally displayed (it
    // renders from the store, so the data is already there).
    setTimeout(() => m.redraw(), 0);
  }

  // Render from the store (the source of truth), not a per-instance array. This
  // is robust to the admin page mounting twice — whichever instance renders
  // reads the same store, so the list never gets stuck behind a desynced flag.
  pagesData() {
    return app.store.all('advanced-pages');
  }

  totalPages() {
    return Math.max(1, Math.ceil(this.pagesData().length / this.perPage));
  }

  // Children of a parent (null = top level), ordered by position then title.
  childrenOf(parentId) {
    const key = parentId == null ? null : Number(parentId);
    return this.pagesData()
      .filter((p) => (p.parentId() == null ? null : Number(p.parentId())) === key)
      .sort((a, b) => (a.position() || 0) - (b.position() || 0) || (a.title() || '').localeCompare(b.title() || ''));
  }

  // Flatten the parent_id tree into ordered { page, depth } rows. Cycle-safe,
  // and any page whose parent is missing is shown at the top level.
  flattenTree() {
    const rows = [];
    const seen = {};
    const walk = (parentId, depth) => {
      this.childrenOf(parentId).forEach((p) => {
        const id = Number(p.id());
        if (seen[id]) return;
        seen[id] = true;
        rows.push({ page: p, depth });
        walk(id, depth + 1);
      });
    };
    walk(null, 0);
    this.pagesData().forEach((p) => {
      if (!seen[Number(p.id())]) {
        seen[Number(p.id())] = true;
        rows.push({ page: p, depth: 0 });
      }
    });
    return rows;
  }

  paginatedRows() {
    const start = (this.currentPage - 1) * this.perPage;
    return this.flattenTree().slice(start, start + this.perPage);
  }

  // --- Drag and drop (reorder + re-parent) ---------------------------------

  onDragStart(e, page) {
    this.dragId = Number(page.id());
    e.dataTransfer.effectAllowed = 'move';
    try {
      e.dataTransfer.setData('text/plain', String(this.dragId));
      const row = e.currentTarget.closest('tr');
      if (row) e.dataTransfer.setDragImage(row, 16, 16);
    } catch (_) {}
  }

  onDragOver(e, page) {
    if (this.dragId == null) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    const id = Number(page.id());
    let next = null;
    if (id !== this.dragId && !this.isDescendantOrSelf(id, this.dragId)) {
      const rect = e.currentTarget.getBoundingClientRect();
      const ratio = (e.clientY - rect.top) / rect.height;
      const zone = ratio < 0.3 ? 'before' : ratio > 0.7 ? 'after' : 'into';
      next = { id, zone };
    }

    // Only re-render when the indicator actually changes (dragover fires a lot).
    const cur = this.dropTarget;
    if (cur && next && cur.id === next.id && cur.zone === next.zone) {
      e.redraw = false;
    } else {
      this.dropTarget = next;
    }
  }

  onDragLeaveRow(e, page) {
    if (this.dropTarget && this.dropTarget.id === Number(page.id()) && !e.currentTarget.contains(e.relatedTarget)) {
      this.dropTarget = null;
    } else {
      e.redraw = false;
    }
  }

  onDrop(e, page) {
    e.preventDefault();
    const target = this.dropTarget;
    const draggedId = this.dragId;
    this.clearDrag();
    if (draggedId != null && target) {
      this.applyDrop(draggedId, target.id, target.zone);
    }
  }

  onDragEnd() {
    this.clearDrag();
  }

  clearDrag() {
    this.dragId = null;
    this.dropTarget = null;
  }

  // True if `id` is the dragged page or one of its descendants (can't drop a
  // page into its own subtree).
  isDescendantOrSelf(id, draggedId) {
    if (id === draggedId) return true;
    let pid = id;
    const seen = {};
    while (pid != null && !seen[pid]) {
      seen[pid] = true;
      const p = app.store.getById('advanced-pages', String(pid));
      const parent = p ? p.parentId() : null;
      if (parent == null) return false;
      if (Number(parent) === draggedId) return true;
      pid = Number(parent);
    }
    return false;
  }

  applyDrop(draggedId, targetId, zone) {
    const dragged = app.store.getById('advanced-pages', String(draggedId));
    const target = app.store.getById('advanced-pages', String(targetId));
    if (!dragged || !target || this.isDescendantOrSelf(targetId, draggedId)) return;

    const newParentId = zone === 'into' ? Number(target.id()) : (target.parentId() == null ? null : Number(target.parentId()));

    // Rebuild the destination sibling order with the dragged page inserted.
    const siblings = this.childrenOf(newParentId).filter((p) => Number(p.id()) !== draggedId);
    let index;
    if (zone === 'into') {
      index = siblings.length;
    } else {
      const ti = siblings.findIndex((p) => Number(p.id()) === targetId);
      index = ti === -1 ? siblings.length : zone === 'before' ? ti : ti + 1;
    }
    siblings.splice(index, 0, dragged);

    // Optimistically renumber the group (and re-parent the dragged page), then
    // persist only the pages that actually changed.
    const changed = [];
    siblings.forEach((p, i) => {
      const isDragged = Number(p.id()) === draggedId;
      const curParent = p.parentId() == null ? null : Number(p.parentId());
      if (p.position() !== i || (isDragged && curParent !== newParentId)) {
        p.pushAttributes({ position: i, parentId: isDragged ? newParentId : curParent });
        changed.push(p);
      }
    });

    this.justMovedId = draggedId;
    m.redraw();

    Promise.all(changed.map((p) => p.save({ position: p.position(), parentId: p.parentId() })))
      .then(() => {
        setTimeout(() => { this.justMovedId = null; m.redraw(); }, 700);
      })
      .catch(() => this.loadPages());
  }

  content() {
    // Only show the spinner during the genuine first load (store still empty).
    if (this.loading && this.pagesData().length === 0) {
      return <LoadingIndicator />;
    }

    const totalPages = this.totalPages();
    if (this.currentPage > totalPages) this.currentPage = totalPages;

    return (
      <div className="AdvancedPagesContent">
        <div className="container">
          <div className="AdvancedPages-support">
            <Button
              className="Button"
              icon="fas fa-heart"
              onclick={() => app.modal.show(SupportModal)}
            >
              {app.translator.trans('tryhackx-advanced-pages.admin.support.button')}
            </Button>
          </div>

          <div className="AdvancedPages-header">
            <Button
              className="Button Button--primary"
              icon="fas fa-plus"
              onclick={() =>
                app.modal.show(EditPageModal, {
                  pages: this.pagesData(),
                  oncreate: () => this.loadPages(),
                })
              }
            >
              {app.translator.trans('tryhackx-advanced-pages.admin.pages.create_button')}
            </Button>
          </div>

          <table className="AdvancedPages-table">
            <thead>
              <tr>
                <th>{app.translator.trans('tryhackx-advanced-pages.admin.pages.title_column')}</th>
                <th>{app.translator.trans('tryhackx-advanced-pages.admin.pages.slug_column')}</th>
                <th>{app.translator.trans('tryhackx-advanced-pages.admin.pages.type_column')}</th>
                <th>{app.translator.trans('tryhackx-advanced-pages.admin.pages.status_column')}</th>
                <th>{app.translator.trans('tryhackx-advanced-pages.admin.pages.groups_column')}</th>
                <th>{app.translator.trans('tryhackx-advanced-pages.admin.pages.actions_column')}</th>
              </tr>
            </thead>
            <tbody>
              {this.pagesData().length === 0 ? (
                <tr>
                  <td colspan="6" className="AdvancedPages-empty">
                    {app.translator.trans('tryhackx-advanced-pages.admin.pages.empty')}
                  </td>
                </tr>
              ) : (
                this.paginatedRows().map((row) => this.pageRow(row.page, row.depth))
              )}
            </tbody>
          </table>

          {this.pagesData().length > 0 && (
            <div className="AdvancedPages-pagination">
              <div className="AdvancedPages-paginationControls">
                <Button
                  className="Button Button--sm"
                  icon="fas fa-chevron-left"
                  disabled={this.currentPage <= 1}
                  onclick={() => { this.currentPage--; }}
                />
                <span className="AdvancedPages-paginationInfo">
                  {this.currentPage} / {totalPages}
                  {' '}({this.pagesData().length})
                </span>
                <Button
                  className="Button Button--sm"
                  icon="fas fa-chevron-right"
                  disabled={this.currentPage >= totalPages}
                  onclick={() => { this.currentPage++; }}
                />
              </div>
              <div className="AdvancedPages-perPageSelect">
                <Select
                  value={String(this.perPage)}
                  options={PER_PAGE_OPTIONS}
                  onchange={(value) => {
                    this.perPage = parseInt(value, 10);
                    this.currentPage = 1;
                    localStorage.setItem('advancedPages.perPage', value);
                  }}
                />
              </div>
            </div>
          )}

          <div className="AdvancedPages-settings">
            <h3>{app.translator.trans('tryhackx-advanced-pages.admin.settings.title')}</h3>
            <p className="helpText">{app.translator.trans('tryhackx-advanced-pages.admin.settings.bbcode_help')}</p>
            <div className="AdvancedPages-settingsGrid">
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'tryhackx-advanced-pages.bbcode_table',
                label: app.translator.trans('tryhackx-advanced-pages.admin.settings.bbcode_table'),
              })}
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'tryhackx-advanced-pages.bbcode_spoiler',
                label: app.translator.trans('tryhackx-advanced-pages.admin.settings.bbcode_spoiler'),
              })}
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'tryhackx-advanced-pages.bbcode_center',
                label: app.translator.trans('tryhackx-advanced-pages.admin.settings.bbcode_center'),
              })}
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'tryhackx-advanced-pages.bbcode_url',
                label: app.translator.trans('tryhackx-advanced-pages.admin.settings.bbcode_url'),
              })}
            </div>
            <h3>{app.translator.trans('tryhackx-advanced-pages.admin.settings.forum_title')}</h3>
            <p className="helpText">{app.translator.trans('tryhackx-advanced-pages.admin.settings.forum_help')}</p>
            <div className="AdvancedPages-settingsGrid">
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'tryhackx-advanced-pages.replace_forum_spoiler',
                label: app.translator.trans('tryhackx-advanced-pages.admin.settings.replace_forum_spoiler'),
              })}
            </div>
            <div className="Form-group Form-controls">
              {this.submitButton()}
              {this.resetButton()}
            </div>
          </div>

        </div>
      </div>
    );
  }

  groupLabels(page) {
    const groups = page.visibleGroups();
    if (!groups || groups.length === 0) {
      return <span className="AdvancedPages-groupLabel AdvancedPages-groupLabel--all">
        {app.translator.trans('tryhackx-advanced-pages.admin.pages.everyone')}
      </span>;
    }

    return groups.map((groupId) => {
      const group = app.store.getById('groups', String(groupId));
      return group ? (
        <span className="AdvancedPages-groupLabel" key={groupId}>
          {group.nameSingular()}
        </span>
      ) : null;
    }).filter(Boolean);
  }

  pageRow(page, depth) {
    const typeLabels = {
      html: 'HTML',
      php: 'PHP',
      bbcode: 'BBCode',
      markdown: 'Markdown',
      text: app.translator.trans('tryhackx-advanced-pages.admin.edit_page.type_text'),
    };

    const id = Number(page.id());
    const dt = this.dropTarget;
    const classes = ['AdvancedPages-row'];
    if (this.dragId === id) classes.push('is-dragging');
    if (dt && dt.id === id) classes.push('drop-' + dt.zone);
    if (this.justMovedId === id) classes.push('just-moved');

    return (
      <tr
        key={page.id()}
        className={classes.join(' ')}
        ondragover={(e) => this.onDragOver(e, page)}
        ondragleave={(e) => this.onDragLeaveRow(e, page)}
        ondrop={(e) => this.onDrop(e, page)}
      >
        <td className="AdvancedPages-title" style={depth ? { paddingLeft: depth * 24 + 12 + 'px' } : null}>
          {depth > 0 && <span className="AdvancedPages-treeMarker">└&nbsp;</span>}
          {page.title()}
        </td>
        <td className="AdvancedPages-slug">
          <code>/p/{page.slug()}</code>
        </td>
        <td>
          <span className={'AdvancedPages-badge AdvancedPages-badge--' + page.contentType()}>
            {typeLabels[page.contentType()] || page.contentType()}
          </span>
        </td>
        <td>
          {page.isPublished() ? (
            <span className="AdvancedPages-status AdvancedPages-status--published">
              {app.translator.trans('tryhackx-advanced-pages.admin.pages.published')}
            </span>
          ) : (
            <span className="AdvancedPages-status AdvancedPages-status--draft">
              {app.translator.trans('tryhackx-advanced-pages.admin.pages.draft')}
            </span>
          )}
          {page.isHidden() && (
            <span className="AdvancedPages-status AdvancedPages-status--hidden">
              {' '}
              {app.translator.trans('tryhackx-advanced-pages.admin.pages.hidden')}
            </span>
          )}
          {page.isRestricted() && (
            <span className="AdvancedPages-status AdvancedPages-status--restricted">
              {' '}
              {app.translator.trans('tryhackx-advanced-pages.admin.pages.restricted')}
            </span>
          )}
        </td>
        <td className="AdvancedPages-groups">
          {this.groupLabels(page)}
        </td>
        <td className="AdvancedPages-actions">
          <Button
            className="Button Button--sm"
            icon="fas fa-pencil-alt"
            onclick={(e) => {
              e.currentTarget.blur();
              app.modal.show(EditPageModal, {
                model: page,
                pages: this.pagesData(),
                onhide: () => this.loadPages(),
              });
            }}
          >
            {app.translator.trans('tryhackx-advanced-pages.admin.pages.edit_button')}
          </Button>
          <a
            className="Button Button--sm"
            href={app.forum.attribute('baseUrl') + '/p/' + page.slug()}
            target="_blank"
          >
            <i className="fas fa-external-link-alt" />
          </a>
          <span
            className="AdvancedPages-dragHandle"
            title={app.translator.trans('tryhackx-advanced-pages.admin.pages.drag_hint')}
            draggable="true"
            ondragstart={(e) => this.onDragStart(e, page)}
            ondragend={() => this.onDragEnd()}
          >
            <i className="fas fa-grip-vertical" />
          </span>
        </td>
      </tr>
    );
  }
}
