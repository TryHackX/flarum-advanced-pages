import app from 'flarum/forum/app';
import { extend as flarumExtend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import addCodeToolbar from './addCodeToolbar';
import pinnedNav from './pinnedNav';

export { default as extend } from './extend';

// Register the pinned-pages nav links (reads the forum payload at render time).
app.initializers.add('tryhackx-advanced-pages-pinned-nav', pinnedNav);

// Opt-in (admin setting): also add the Select / Copy code toolbar to the code
// blocks of regular forum posts. The setting is read at render time — app.forum
// is not populated yet while initializers run, so the hook is always registered
// and simply no-ops when the setting is off.
app.initializers.add('tryhackx-advanced-pages-code-buttons', () => {
  const apply = function (_retval, vnode) {
    if (!app.forum || !app.forum.attribute('tryhackx-advanced-pages.code_buttons_in_posts')) return;
    const dom = (vnode && vnode.dom) || this.element;
    if (dom) addCodeToolbar(dom.querySelector('.Post-body'));
  };

  flarumExtend(CommentPost.prototype, 'oncreate', apply);
  flarumExtend(CommentPost.prototype, 'onupdate', apply);
});
