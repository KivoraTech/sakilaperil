"use client";

import { useEffect, useId, useRef, useState } from "react";
import { EditorContent, useEditor, useEditorState, type Editor } from "@tiptap/react";
import {
  Bold,
  Heading2,
  Heading3,
  ImagePlus,
  Italic,
  Link as LinkIcon,
  List,
  ListOrdered,
  Minus,
  Quote,
  Redo2,
  Strikethrough,
  Underline,
  Undo2,
  Unlink,
  Video,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MediaPicker } from "@/components/admin/media/MediaPicker";
import { SAFE_HREF } from "@/lib/blocks/fields";
import { emptyDoc, type RichTextDoc } from "@/lib/richtext/schema";
import { parseVideoUrl } from "@/lib/richtext/video";
import { cn } from "@/lib/utils";
import { editorExtensions } from "./rich-text-extensions";
import "./rich-text-editor.css";

/**
 * Tiptap rich-text editor (JSON in, JSON out). Headings, bold/italic/underline/strike,
 * lists, quotes, links (safe protocols only), images from the media library and
 * YouTube/Vimeo embeds. The server sanitises the JSON again on save.
 *
 *   <RichTextEditor value={doc} onChange={setDoc} labelledBy="body-label" />
 */
export type RichTextEditorProps = {
  value: RichTextDoc | null | undefined;
  onChange: (doc: RichTextDoc) => void;
  /** id of the visible label element (accessibility). */
  labelledBy?: string;
  describedBy?: string;
  invalid?: boolean;
  minHeight?: number;
  className?: string;
};

export function RichTextEditor({
  value,
  onChange,
  labelledBy,
  describedBy,
  invalid,
  minHeight = 260,
  className,
}: RichTextEditorProps) {
  const onChangeRef = useRef(onChange);
  useEffect(() => {
    onChangeRef.current = onChange;
  }, [onChange]);

  const editor = useEditor({
    extensions: editorExtensions(),
    content: value ?? emptyDoc(),
    immediatelyRender: false,
    editorProps: {
      attributes: {
        class: "rt-editor-content focus:outline-none",
        role: "textbox",
        "aria-multiline": "true",
        ...(labelledBy ? { "aria-labelledby": labelledBy } : {}),
        ...(describedBy ? { "aria-describedby": describedBy } : {}),
        ...(invalid ? { "aria-invalid": "true" } : {}),
      },
    },
    onUpdate: ({ editor: e }) => onChangeRef.current(e.getJSON() as RichTextDoc),
  });

  return (
    <div
      className={cn(
        "rounded-lg border bg-background focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50",
        invalid && "border-destructive",
        className,
      )}
    >
      {editor ? <Toolbar editor={editor} /> : <div className="h-10 border-b" />}
      <div className="rt-editor px-4 py-3" style={{ minHeight }}>
        <EditorContent editor={editor} />
      </div>
    </div>
  );
}

function ToolButton({
  label,
  active,
  disabled,
  onClick,
  children,
}: {
  label: string;
  active?: boolean;
  disabled?: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-sm"
      title={label}
      aria-label={label}
      aria-pressed={active === undefined ? undefined : active}
      disabled={disabled}
      onMouseDown={(e) => e.preventDefault()}
      onClick={onClick}
      className={cn(active && "bg-muted text-foreground")}
    >
      {children}
    </Button>
  );
}

function Toolbar({ editor }: { editor: Editor }) {
  const state = useEditorState({
    editor,
    selector: ({ editor: e }) => ({
      bold: e.isActive("bold"),
      italic: e.isActive("italic"),
      underline: e.isActive("underline"),
      strike: e.isActive("strike"),
      h2: e.isActive("heading", { level: 2 }),
      h3: e.isActive("heading", { level: 3 }),
      bullet: e.isActive("bulletList"),
      ordered: e.isActive("orderedList"),
      quote: e.isActive("blockquote"),
      link: e.isActive("link"),
      canUndo: e.can().undo(),
      canRedo: e.can().redo(),
    }),
  });
  const [linkOpen, setLinkOpen] = useState(false);
  const [videoOpen, setVideoOpen] = useState(false);
  const [imageOpen, setImageOpen] = useState(false);
  const chain = () => editor.chain().focus();

  return (
    <>
      <div
        role="toolbar"
        aria-label="Formatting"
        className="sticky top-0 z-10 flex flex-wrap items-center gap-0.5 rounded-t-lg border-b bg-muted/40 p-1"
      >
        <ToolButton
          label="Heading"
          active={state.h2}
          onClick={() => chain().toggleHeading({ level: 2 }).run()}
        >
          <Heading2 />
        </ToolButton>
        <ToolButton
          label="Subheading"
          active={state.h3}
          onClick={() => chain().toggleHeading({ level: 3 }).run()}
        >
          <Heading3 />
        </ToolButton>
        <span className="mx-1 h-5 w-px bg-border" aria-hidden />
        <ToolButton label="Bold" active={state.bold} onClick={() => chain().toggleBold().run()}>
          <Bold />
        </ToolButton>
        <ToolButton label="Italic" active={state.italic} onClick={() => chain().toggleItalic().run()}>
          <Italic />
        </ToolButton>
        <ToolButton
          label="Underline"
          active={state.underline}
          onClick={() => chain().toggleUnderline().run()}
        >
          <Underline />
        </ToolButton>
        <ToolButton label="Strikethrough" active={state.strike} onClick={() => chain().toggleStrike().run()}>
          <Strikethrough />
        </ToolButton>
        <span className="mx-1 h-5 w-px bg-border" aria-hidden />
        <ToolButton
          label="Bulleted list"
          active={state.bullet}
          onClick={() => chain().toggleBulletList().run()}
        >
          <List />
        </ToolButton>
        <ToolButton
          label="Numbered list"
          active={state.ordered}
          onClick={() => chain().toggleOrderedList().run()}
        >
          <ListOrdered />
        </ToolButton>
        <ToolButton label="Quote" active={state.quote} onClick={() => chain().toggleBlockquote().run()}>
          <Quote />
        </ToolButton>
        <ToolButton label="Divider" onClick={() => chain().setHorizontalRule().run()}>
          <Minus />
        </ToolButton>
        <span className="mx-1 h-5 w-px bg-border" aria-hidden />
        <ToolButton
          label={state.link ? "Edit link" : "Add link"}
          active={state.link}
          onClick={() => setLinkOpen(true)}
        >
          <LinkIcon />
        </ToolButton>
        {state.link ? (
          <ToolButton label="Remove link" onClick={() => chain().extendMarkRange("link").unsetLink().run()}>
            <Unlink />
          </ToolButton>
        ) : null}
        <ToolButton label="Insert image from media library" onClick={() => setImageOpen(true)}>
          <ImagePlus />
        </ToolButton>
        <ToolButton label="Embed YouTube or Vimeo video" onClick={() => setVideoOpen(true)}>
          <Video />
        </ToolButton>
        <span className="ml-auto flex">
          <ToolButton label="Undo" disabled={!state.canUndo} onClick={() => chain().undo().run()}>
            <Undo2 />
          </ToolButton>
          <ToolButton label="Redo" disabled={!state.canRedo} onClick={() => chain().redo().run()}>
            <Redo2 />
          </ToolButton>
        </span>
      </div>

      <LinkDialog editor={editor} open={linkOpen} onOpenChange={setLinkOpen} />
      <VideoDialog editor={editor} open={videoOpen} onOpenChange={setVideoOpen} />
      <MediaPicker
        open={imageOpen}
        onOpenChange={setImageOpen}
        accept="IMAGE"
        title="Insert image"
        onSelect={(asset) => {
          editor
            .chain()
            .focus()
            .insertContent({
              type: "image",
              attrs: {
                src: asset.url,
                alt: asset.alt.it,
                width: asset.width,
                height: asset.height,
                assetId: asset.id,
              },
            })
            .run();
        }}
      />
    </>
  );
}

function LinkDialog({
  editor,
  open,
  onOpenChange,
}: {
  editor: Editor;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Link</DialogTitle>
          <DialogDescription>
            A web address (https://…), a page on this site (/projects), mailto: or tel:. Leave empty to
            remove.
          </DialogDescription>
        </DialogHeader>
        {/* Mounted only while open, so its state starts fresh each time. */}
        {open ? <LinkForm editor={editor} onDone={() => onOpenChange(false)} /> : null}
      </DialogContent>
    </Dialog>
  );
}

function LinkForm({ editor, onDone }: { editor: Editor; onDone: () => void }) {
  const id = useId();
  const [href, setHref] = useState(() => (editor.getAttributes("link").href as string | undefined) ?? "");
  const [error, setError] = useState<string | null>(null);

  const apply = () => {
    let value = href.trim();
    if (!value) {
      editor.chain().focus().extendMarkRange("link").unsetLink().run();
      onDone();
      return;
    }
    if (/^www\./i.test(value)) value = `https://${value}`;
    if (!SAFE_HREF.test(value)) {
      setError("Use https://…, a site path like /projects, mailto: or tel:");
      return;
    }
    const { empty } = editor.state.selection;
    const chain = editor.chain().focus().extendMarkRange("link");
    if (empty && !editor.isActive("link")) {
      chain
        .insertContent({ type: "text", text: value, marks: [{ type: "link", attrs: { href: value } }] })
        .run();
    } else {
      chain.setLink({ href: value }).run();
    }
    onDone();
  };

  return (
    <div className="grid gap-2">
      <Label htmlFor={`${id}-href`}>Address</Label>
      <Input
        id={`${id}-href`}
        value={href}
        onChange={(e) => setHref(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            apply();
          }
        }}
        placeholder="https://"
        autoFocus
        aria-invalid={!!error}
        aria-describedby={error ? `${id}-err` : undefined}
      />
      {error ? (
        <p id={`${id}-err`} role="alert" className="text-sm text-destructive">
          {error}
        </p>
      ) : null}
      <DialogFooter className="mt-2">
        <Button type="button" variant="outline" onClick={onDone}>
          Cancel
        </Button>
        <Button type="button" onClick={apply}>
          Apply
        </Button>
      </DialogFooter>
    </div>
  );
}

function VideoDialog({
  editor,
  open,
  onOpenChange,
}: {
  editor: Editor;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Embed a video</DialogTitle>
          <DialogDescription>
            Only YouTube and Vimeo are supported. Videos are not uploaded to the site.
          </DialogDescription>
        </DialogHeader>
        {open ? <VideoForm editor={editor} onDone={() => onOpenChange(false)} /> : null}
      </DialogContent>
    </Dialog>
  );
}

function VideoForm({ editor, onDone }: { editor: Editor; onDone: () => void }) {
  const id = useId();
  const [url, setUrl] = useState("");
  const [error, setError] = useState<string | null>(null);

  const apply = () => {
    const parsed = parseVideoUrl(url);
    if (!parsed) {
      setError("Paste a YouTube or Vimeo link, e.g. https://www.youtube.com/watch?v=…");
      return;
    }
    editor
      .chain()
      .focus()
      .insertContent({ type: "videoEmbed", attrs: { provider: parsed.provider, videoId: parsed.id } })
      .run();
    onDone();
  };

  return (
    <div className="grid gap-2">
      <Label htmlFor={`${id}-url`}>Video link</Label>
      <Input
        id={`${id}-url`}
        value={url}
        onChange={(e) => setUrl(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            apply();
          }
        }}
        placeholder="https://www.youtube.com/watch?v=…"
        autoFocus
        aria-invalid={!!error}
        aria-describedby={error ? `${id}-err` : undefined}
      />
      {error ? (
        <p id={`${id}-err`} role="alert" className="text-sm text-destructive">
          {error}
        </p>
      ) : null}
      <DialogFooter className="mt-2">
        <Button type="button" variant="outline" onClick={onDone}>
          Cancel
        </Button>
        <Button type="button" onClick={apply}>
          Embed
        </Button>
      </DialogFooter>
    </div>
  );
}
