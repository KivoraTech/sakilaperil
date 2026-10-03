"use client";

import { EditorContent, useEditor, useEditorState } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import {
  Bold,
  Heading2,
  Heading3,
  Italic,
  Link2,
  List,
  ListOrdered,
  Quote,
  Redo2,
  Undo2,
} from "lucide-react";
import { SAFE_HREF } from "@/lib/blocks/fields";
import type { RichTextDoc } from "@/lib/richtext/schema";
import { cn } from "@/lib/utils";

/**
 * Minimal Tiptap editor for the richText block. Stores ProseMirror JSON (never HTML).
 * Only headings 2–3, paragraphs, bold/italic, lists, quotes and SAFE links are enabled so the
 * whitelist renderer (lib/richtext) can render everything it produces.
 * Can be swapped for a shared editor without changing the stored format.
 */
export function RichTextField({
  value,
  onChange,
  labelledBy,
  invalid,
}: {
  value: RichTextDoc | undefined;
  onChange: (doc: RichTextDoc) => void;
  labelledBy?: string;
  invalid?: boolean;
}) {
  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
        code: false,
        codeBlock: false,
        horizontalRule: false,
        strike: false,
        underline: false,
        link: {
          openOnClick: false,
          autolink: true,
          defaultProtocol: "https",
          protocols: ["mailto", "tel"],
          isAllowedUri: (url) => SAFE_HREF.test(url),
        },
      }),
    ],
    content: value && value.content?.length ? value : { type: "doc", content: [{ type: "paragraph" }] },
    editorProps: {
      attributes: {
        class: "prose prose-sm max-w-none min-h-40 px-3 py-2 outline-none",
        ...(labelledBy ? { "aria-labelledby": labelledBy } : {}),
        "aria-multiline": "true",
        role: "textbox",
      },
    },
    onUpdate: ({ editor }) => onChange(editor.getJSON() as RichTextDoc),
  });

  const state = useEditorState({
    editor,
    selector: ({ editor: e }) =>
      e
        ? {
            bold: e.isActive("bold"),
            italic: e.isActive("italic"),
            h2: e.isActive("heading", { level: 2 }),
            h3: e.isActive("heading", { level: 3 }),
            bullet: e.isActive("bulletList"),
            ordered: e.isActive("orderedList"),
            quote: e.isActive("blockquote"),
            link: e.isActive("link"),
          }
        : null,
  });

  const setLink = () => {
    if (!editor) return;
    const previous = editor.getAttributes("link").href as string | undefined;
    const url = window.prompt(
      "Link (https://…, /page, #anchor, mailto: or tel:). Leave empty to remove.",
      previous ?? "",
    );
    if (url === null) return;
    if (url.trim() === "") {
      editor.chain().focus().extendMarkRange("link").unsetLink().run();
      return;
    }
    if (!SAFE_HREF.test(url.trim())) {
      window.alert(
        "This link is not allowed. Use https://…, a path starting with /, #anchor, mailto: or tel:.",
      );
      return;
    }
    editor.chain().focus().extendMarkRange("link").setLink({ href: url.trim() }).run();
  };

  const tools = [
    { label: "Bold", icon: Bold, active: state?.bold, run: () => editor?.chain().focus().toggleBold().run() },
    {
      label: "Italic",
      icon: Italic,
      active: state?.italic,
      run: () => editor?.chain().focus().toggleItalic().run(),
    },
    {
      label: "Heading",
      icon: Heading2,
      active: state?.h2,
      run: () => editor?.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
      label: "Subheading",
      icon: Heading3,
      active: state?.h3,
      run: () => editor?.chain().focus().toggleHeading({ level: 3 }).run(),
    },
    {
      label: "Bulleted list",
      icon: List,
      active: state?.bullet,
      run: () => editor?.chain().focus().toggleBulletList().run(),
    },
    {
      label: "Numbered list",
      icon: ListOrdered,
      active: state?.ordered,
      run: () => editor?.chain().focus().toggleOrderedList().run(),
    },
    {
      label: "Quote",
      icon: Quote,
      active: state?.quote,
      run: () => editor?.chain().focus().toggleBlockquote().run(),
    },
    { label: "Link", icon: Link2, active: state?.link, run: setLink },
    { label: "Undo", icon: Undo2, active: false, run: () => editor?.chain().focus().undo().run() },
    { label: "Redo", icon: Redo2, active: false, run: () => editor?.chain().focus().redo().run() },
  ];

  return (
    <div className={cn("rounded-lg border bg-background", invalid && "border-destructive")}>
      <div role="toolbar" aria-label="Text formatting" className="flex flex-wrap gap-0.5 border-b p-1">
        {tools.map((t) => (
          <button
            key={t.label}
            type="button"
            onClick={t.run}
            aria-label={t.label}
            aria-pressed={t.active ?? undefined}
            title={t.label}
            className={cn(
              "inline-flex size-8 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50",
              t.active && "bg-muted text-foreground",
            )}
          >
            <t.icon className="size-4" aria-hidden />
          </button>
        ))}
      </div>
      <EditorContent
        editor={editor}
        className="[&_.ProseMirror_a]:underline [&_.ProseMirror_blockquote]:border-l-2 [&_.ProseMirror_blockquote]:pl-3 [&_.ProseMirror_h2]:text-lg [&_.ProseMirror_h2]:font-semibold [&_.ProseMirror_h3]:font-semibold [&_.ProseMirror_ol]:list-decimal [&_.ProseMirror_ol]:pl-5 [&_.ProseMirror_p]:my-1.5 [&_.ProseMirror_ul]:list-disc [&_.ProseMirror_ul]:pl-5"
      />
    </div>
  );
}
