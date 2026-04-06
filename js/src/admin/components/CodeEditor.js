import Component from 'flarum/common/Component';
import { EditorView, basicSetup } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { html } from '@codemirror/lang-html';
import { php } from '@codemirror/lang-php';
import { oneDark } from '@codemirror/theme-one-dark';

export default class CodeEditor extends Component {
  oncreate(vnode) {
    super.oncreate(vnode);
    this.createEditor(vnode.dom);
  }

  onupdate(vnode) {
    if (this.currentLang !== this.attrs.language) {
      this.destroyEditor();
      this.createEditor(vnode.dom);
    }

    if (this.editorView && this.attrs.value !== undefined) {
      const currentContent = this.editorView.state.doc.toString();
      if (currentContent !== this.attrs.value && !this.internalUpdate) {
        this.editorView.dispatch({
          changes: { from: 0, to: currentContent.length, insert: this.attrs.value },
        });
      }
    }
  }

  onremove(vnode) {
    this.destroyEditor();
  }

  createEditor(container) {
    const lang = this.attrs.language || 'html';
    this.currentLang = lang;

    const langExtension = lang === 'php' ? php() : html();

    const extensions = [
      basicSetup,
      langExtension,
      oneDark,
      EditorView.updateListener.of((update) => {
        if (update.docChanged && this.attrs.onchange) {
          this.internalUpdate = true;
          this.attrs.onchange(update.state.doc.toString());
          this.internalUpdate = false;
        }
      }),
      EditorView.theme({
        '&': {
          minHeight: '300px',
          maxHeight: '500px',
          fontSize: '13px',
        },
        '.cm-scroller': {
          overflow: 'auto',
          fontFamily: "'Fira Code', 'Consolas', 'Monaco', monospace",
        },
        '.cm-content': {
          minHeight: '300px',
        },
      }),
    ];

    this.editorView = new EditorView({
      state: EditorState.create({
        doc: this.attrs.value || '',
        extensions,
      }),
      parent: container,
    });
  }

  destroyEditor() {
    if (this.editorView) {
      this.editorView.destroy();
      this.editorView = null;
    }
  }

  view() {
    return <div className="AdvancedPages-codeEditor"></div>;
  }
}
