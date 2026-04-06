import app from 'flarum/admin/app';
import Page from '../common/models/Page';
import AdvancedPagesPage from './components/AdvancedPagesPage';

app.initializers.add('tryhackx-advanced-pages', () => {
  app.store.models['advanced-pages'] = Page;

  app.extensionData
    .for('tryhackx-advanced-pages')
    .registerPage(AdvancedPagesPage)
    .registerPermission(
      {
        icon: 'fas fa-file-code',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.manage_pages_label'),
        permission: 'advancedPages.manage',
      },
      'moderate',
      70
    )
    .registerPermission(
      {
        icon: 'fas fa-eye-slash',
        label: app.translator.trans('tryhackx-advanced-pages.admin.permissions.view_spoilers_label'),
        permission: 'advancedPages.viewSpoilers',
      },
      'view',
      90
    );
});
