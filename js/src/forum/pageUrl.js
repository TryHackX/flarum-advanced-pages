import app from 'flarum/forum/app';

/**
 * Path of an Advanced Page (/p/{slug}) including the forum's base path, so links
 * also work when the forum lives in a subfolder (example.com/forum/p/…).
 *
 * Not app.route(): Flarum's route() fills ":param" placeholders by name and does
 * not understand Mithril's variadic ":slug..." used for nested slugs — it would
 * produce "/p/undefined?slug=…".
 */
export default function pageUrl(slug) {
  return (app.forum.attribute('basePath') || '') + '/p/' + slug;
}
