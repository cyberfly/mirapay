import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('richEditor', (content) => ({
        content,
        editor: null,
        version: 0,

        init() {
            const self = this;

            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer nofollow' } },
                    }),
                ],
                content: this.content,
                editorProps: {
                    attributes: {
                        class: 'min-h-40 rounded-b-lg px-3 py-2 focus:outline-hidden [&_a]:text-sky-600 [&_a]:underline [&_blockquote]:border-s-4 [&_blockquote]:border-sky-200 [&_blockquote]:ps-3 [&_h2]:mt-4 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:text-lg [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-2 [&_ul]:list-disc [&_ul]:ps-6',
                    },
                },
                onUpdate({ editor }) {
                    self.content = editor.isEmpty ? '' : editor.getHTML();
                },
                onTransaction() {
                    self.version++;
                },
            });
        },

        isActive(name, attrs = {}) {
            this.version;

            return this.editor?.isActive(name, attrs) ?? false;
        },

        setLink() {
            const previous = this.editor.getAttributes('link').href ?? '';
            const url = window.prompt('URL pautan (kosongkan untuk buang)', previous);

            if (url === null) {
                return;
            }

            if (url === '') {
                this.editor.chain().focus().extendMarkRange('link').unsetLink().run();

                return;
            }

            this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        },

        destroy() {
            this.editor?.destroy();
        },
    }));
});
