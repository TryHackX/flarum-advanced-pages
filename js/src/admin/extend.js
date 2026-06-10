import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import commonExtend from '../common/extend';
import AdvancedPagesPage from './components/AdvancedPagesPage';

export default [
  ...commonExtend,

  new Extend.Admin()
    .page(AdvancedPagesPage)
    .permission(
      () => ({
        icon: 'fas fa-file-code',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.manage_pages_label'),
        permission: 'advancedPages.manage',
      }),
      'moderate',
      70
    )
    // Per-content-type creation permissions. Only the "safe" types (escaped /
    // formatter-parsed output) are exposed in the admin grid. The code/script
    // capable types (HTML, PHP) are intentionally NOT here — they can only be
    // granted via the `php flarum advancedpages:permission` console command, so
    // they can never be enabled by an accidental click in the panel.
    .permission(
      () => ({
        icon: 'fas fa-font',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.create_text_label'),
        permission: 'advancedPages.create.text',
      }),
      'start',
      67
    )
    .permission(
      () => ({
        icon: 'fas fa-code',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.create_bbcode_label'),
        permission: 'advancedPages.create.bbcode',
      }),
      'start',
      66
    )
    .permission(
      () => ({
        icon: 'fab fa-markdown',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.create_markdown_label'),
        permission: 'advancedPages.create.markdown',
      }),
      'start',
      65
    )
    .permission(
      () => ({
        icon: 'fas fa-eye-slash',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.view_spoilers_label'),
        permission: 'advancedPages.viewSpoilers',
      }),
      'view',
      90
    ),
];
