import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import PageView from './components/PageView';

export default [
  ...commonExtend,

  new Extend.Routes()
    .add('tryhackx-advanced-pages.page', '/p/:slug', PageView),
];
