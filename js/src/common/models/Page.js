import Model from 'flarum/common/Model';

export default class Page extends Model {
  title() {
    return Model.attribute('title').call(this);
  }

  slug() {
    return Model.attribute('slug').call(this);
  }

  content() {
    return Model.attribute('content').call(this);
  }

  contentType() {
    return Model.attribute('contentType').call(this);
  }

  contentHtml() {
    return Model.attribute('contentHtml').call(this);
  }

  newlineMode() {
    return Model.attribute('newlineMode').call(this);
  }

  isPublished() {
    return Model.attribute('isPublished').call(this);
  }

  isHidden() {
    return Model.attribute('isHidden').call(this);
  }

  isRestricted() {
    return Model.attribute('isRestricted').call(this);
  }

  metaDescription() {
    return Model.attribute('metaDescription').call(this);
  }

  visibleGroups() {
    return Model.attribute('visibleGroups').call(this);
  }

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }

  user() {
    return Model.hasOne('user').call(this);
  }

  editUser() {
    return Model.hasOne('editUser').call(this);
  }
}
