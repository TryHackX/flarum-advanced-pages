import app from 'flarum/forum/app';
import Page from '../common/models/Page';
import PageView from './components/PageView';

app.initializers.add('tryhackx-advanced-pages', () => {
  app.store.models['advanced-pages'] = Page;

  app.routes['tryhackx-advanced-pages.page'] = {
    path: '/p/:slug',
    component: PageView,
  };
});
