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
