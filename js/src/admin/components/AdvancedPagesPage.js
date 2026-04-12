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
    this.pages = [];
    this.currentPage = 1;
    this.perPage = parseInt(localStorage.getItem('advancedPages.perPage') || '30', 10);

    this.loadPages();
  }

  loadPages() {
    this.loading = true;

    app.store.find('advanced-pages').then((pages) => {
      this.pages = pages;
      this.loading = false;
      m.redraw();
    });
  }

  totalPages() {
    return Math.max(1, Math.ceil(this.pages.length / this.perPage));
  }

  paginatedPages() {
    const start = (this.currentPage - 1) * this.perPage;
    return this.pages.slice(start, start + this.perPage);
  }

  content() {
    if (this.loading) {
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
                  onsaved: () => this.loadPages(),
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
              {this.pages.length === 0 ? (
                <tr>
                  <td colspan="6" className="AdvancedPages-empty">
                    {app.translator.trans('tryhackx-advanced-pages.admin.pages.empty')}
                  </td>
                </tr>
              ) : (
                this.paginatedPages().map((page) => this.pageRow(page))
              )}
            </tbody>
          </table>

          {this.pages.length > 0 && (
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
                  {' '}({this.pages.length})
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
            {this.submitButton()}
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

  pageRow(page) {
    const typeLabels = {
      html: 'HTML',
      php: 'PHP',
      bbcode: 'BBCode',
      markdown: 'Markdown',
      text: app.translator.trans('tryhackx-advanced-pages.admin.edit_page.type_text'),
    };

    return (
      <tr key={page.id()}>
        <td className="AdvancedPages-title">{page.title()}</td>
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
                onsaved: () => this.loadPages(),
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
        </td>
      </tr>
    );
  }
}
