import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import PageView from './components/PageView';

export default [
  ...commonExtend,

  // ":slug..." is a Mithril variadic param so nested paths (docs/getting-started)
  // are captured whole, matching the backend's /p/{slug:.+} route.
  new Extend.Routes()
    .add('tryhackx-advanced-pages.page', '/p/:slug...', PageView),
];
