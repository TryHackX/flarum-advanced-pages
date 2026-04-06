import Component from 'flarum/common/Component';
import TextEditor from 'flarum/common/components/TextEditor';
import Stream from 'flarum/common/utils/Stream';

export default class FormattedEditor extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.contentStream = Stream(this.attrs.value || '');

    this.composer = {
      editor: null,
      fields: {
        content: this.contentStream,
      },
      bodyMatches: () => false,
    };
  }

  onbeforeupdate(vnode) {
    const externalValue = vnode.attrs.value || '';
    if (externalValue !== this.contentStream()) {
      this.contentStream(externalValue);
    }
  }

  view() {
    return (
      <div className="AdvancedPages-formattedEditor">
        <TextEditor
          composer={this.composer}
          submitLabel=""
          value={this.contentStream()}
          placeholder=""
          disabled={false}
          onchange={(value) => {
            this.contentStream(value);
            if (this.attrs.onchange) {
              this.attrs.onchange(value);
            }
          }}
          onsubmit={() => {}}
        />
      </div>
    );
  }
}
