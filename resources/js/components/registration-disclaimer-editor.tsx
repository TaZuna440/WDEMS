import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Button } from '@/components/ui/button';

export default function RegistrationDisclaimerEditor({ value, onChange }: { value: string; onChange: (html: string) => void }) {
    const editor = useEditor({
        extensions: [StarterKit.configure({
            heading: { levels: [1, 2] },
            blockquote: false, bulletList: false, orderedList: false,
            listItem: false, code: false, codeBlock: false,
            horizontalRule: false, strike: false, link: false, underline: false,
        })],
        content: value,
        immediatelyRender: false,
        editorProps: { attributes: {
            class: 'min-h-64 max-h-[50vh] overflow-y-auto rounded-md border border-border p-4 outline-none [&_h1]:text-2xl [&_h2]:text-xl [&_p]:my-3',
            'aria-label': 'Participant disclaimer',
        } },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    return <div className="space-y-3">
        <div role="toolbar" aria-label="Disclaimer formatting" className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => editor?.chain().focus().setParagraph().run()}>Paragraph</Button>
            <Button type="button" variant="outline" size="sm" onClick={() => editor?.chain().focus().toggleHeading({ level: 1 }).run()}>H1</Button>
            <Button type="button" variant="outline" size="sm" onClick={() => editor?.chain().focus().toggleHeading({ level: 2 }).run()}>H2</Button>
            <Button type="button" variant="outline" size="sm" onClick={() => editor?.chain().focus().toggleBold().run()}>Bold</Button>
            <Button type="button" variant="outline" size="sm" onClick={() => editor?.chain().focus().toggleItalic().run()}>Italic</Button>
        </div>
        <EditorContent editor={editor} />
        <p className="text-xs text-muted-foreground">Changes are saved with the registration form.</p>
    </div>;
}
