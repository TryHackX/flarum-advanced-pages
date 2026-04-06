import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class ConfirmModal extends Modal {
  className() {
    return 'ConfirmModal Modal--small';
  }

  title() {
    return this.attrs.title || 'Confirm';
  }

  content() {
    return (
      <div className="Modal-body">
        <p>{this.attrs.message}</p>
        <div style="text-align: center; margin-top: 15px;">
          <Button
            className="Button"
            onclick={() => this.hide()}
          >
            {this.attrs.cancelText || 'Cancel'}
          </Button>
          {' '}
          <Button
            className="Button Button--danger"
            onclick={() => {
              this.hide();
              if (this.attrs.onconfirm) this.attrs.onconfirm();
            }}
          >
            {this.attrs.confirmText || 'Confirm'}
          </Button>
        </div>
      </div>
    );
  }
}
