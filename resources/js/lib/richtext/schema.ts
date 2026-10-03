import { z } from "zod";
import { SAFE_HREF } from "@/lib/blocks/fields";
import { isValidVideoId } from "./video";

/**
 * Tiptap / ProseMirror JSON document. Stored as JSON (never HTML) and rendered by the
 * allow-list renderer in lib/richtext/render.tsx.
 *
 * - `richTextDocSchema` is the lenient STRUCTURAL schema (used by block schemas and Zod
 *   input validation: bounded sizes, right shape).
 * - `sanitizeRichText()` is the STRICT allow-list pass: unknown nodes/marks/attrs are
 *   dropped, links must match SAFE_HREF, videos must be valid YouTube/Vimeo ids, images
 *   must point at our uploads or https. Server actions run it before saving and the
 *   renderer runs it again before rendering (defence in depth).
 */
export type RichTextNode = {
  type: string;
  attrs?: Record<string, unknown>;
  content?: RichTextNode[];
  marks?: { type: string; attrs?: Record<string, unknown> }[];
  text?: string;
};

const node: z.ZodType<RichTextNode> = z.lazy(() =>
  z.object({
    type: z.string().min(1).max(50),
    attrs: z.record(z.string(), z.unknown()).optional(),
    content: z.array(node).max(2000).optional(),
    marks: z
      .array(z.object({ type: z.string().max(50), attrs: z.record(z.string(), z.unknown()).optional() }))
      .max(20)
      .optional(),
    text: z.string().max(20000).optional(),
  }),
);

export const richTextDocSchema = z.object({
  type: z.literal("doc"),
  content: z.array(node).max(2000).default([]),
});
export type RichTextDoc = z.infer<typeof richTextDocSchema>;

export const emptyDoc = (): RichTextDoc => ({ type: "doc", content: [] });

/** Build a simple doc from plain paragraphs (seed / tests). */
export function docFromParagraphs(paragraphs: string[]): RichTextDoc {
  return {
    type: "doc",
    content: paragraphs.map((p) => ({ type: "paragraph", content: [{ type: "text", text: p }] })),
  };
}

// ───────────────────────── Allow-list ─────────────────────────

/** Block/inline node types the editor produces and the renderer understands. */
export const ALLOWED_NODES = [
  "doc",
  "paragraph",
  "text",
  "heading",
  "bulletList",
  "orderedList",
  "listItem",
  "blockquote",
  "hardBreak",
  "horizontalRule",
  "image",
  "videoEmbed",
] as const;
export type AllowedNode = (typeof ALLOWED_NODES)[number];

export const ALLOWED_MARKS = ["bold", "italic", "underline", "strike", "link"] as const;
export type AllowedMark = (typeof ALLOWED_MARKS)[number];

const nodeSet = new Set<string>(ALLOWED_NODES);
const markSet = new Set<string>(ALLOWED_MARKS);

/** Headings inside body text start at h2 (the page title is the h1). */
export const HEADING_LEVELS = [2, 3, 4] as const;

const MAX_DEPTH = 12;
const MAX_NODES = 5000;

/** Image sources we render: our own uploads (local route) or https (object storage / CDN). */
export function isSafeImageSrc(src: unknown): src is string {
  if (typeof src !== "string" || src.length > 1000) return false;
  if (/[\s\\"'<>]/.test(src)) return false;
  return /^\/uploads\/[A-Za-z0-9/_.-]+$/.test(src) || /^https:\/\/[^/]+\//.test(src);
}

export function isSafeHref(href: unknown): href is string {
  return typeof href === "string" && href.length <= 500 && SAFE_HREF.test(href.trim());
}

const str = (v: unknown, max: number): string | undefined =>
  typeof v === "string" && v.trim() ? v.trim().slice(0, max) : undefined;
const posInt = (v: unknown, max: number): number | undefined =>
  typeof v === "number" && Number.isInteger(v) && v > 0 && v <= max ? v : undefined;

type Counter = { n: number };

function cleanMarks(marks: RichTextNode["marks"]): RichTextNode["marks"] | undefined {
  if (!Array.isArray(marks)) return undefined;
  const out: NonNullable<RichTextNode["marks"]> = [];
  const seen = new Set<string>();
  for (const m of marks.slice(0, 20)) {
    if (!m || typeof m.type !== "string" || !markSet.has(m.type) || seen.has(m.type)) continue;
    if (m.type === "link") {
      const href = m.attrs?.href;
      if (!isSafeHref(href)) continue; // unsafe link → text survives without the link
      out.push({ type: "link", attrs: { href: href.trim() } });
    } else {
      out.push({ type: m.type });
    }
    seen.add(m.type);
  }
  return out.length ? out : undefined;
}

function cleanNode(raw: unknown, depth: number, counter: Counter): RichTextNode | null {
  if (!raw || typeof raw !== "object" || depth > MAX_DEPTH) return null;
  if (++counter.n > MAX_NODES) return null;
  const n = raw as RichTextNode;
  if (typeof n.type !== "string" || !nodeSet.has(n.type) || n.type === "doc") return null;
  const type = n.type as AllowedNode;

  const children = () =>
    Array.isArray(n.content)
      ? n.content.map((c) => cleanNode(c, depth + 1, counter)).filter((c): c is RichTextNode => !!c)
      : [];

  switch (type) {
    case "text": {
      if (typeof n.text !== "string" || n.text.length === 0) return null;
      const marks = cleanMarks(n.marks);
      return { type, text: n.text.slice(0, 20000), ...(marks ? { marks } : {}) };
    }
    case "hardBreak":
    case "horizontalRule":
      return { type };
    case "heading": {
      const lvl = Number(n.attrs?.level);
      const level = (HEADING_LEVELS as readonly number[]).includes(lvl) ? lvl : lvl > 4 ? 4 : 2;
      return { type, attrs: { level }, content: children() };
    }
    case "orderedList": {
      const start = posInt(n.attrs?.start, 10000) ?? 1;
      return { type, attrs: { start }, content: children().filter((c) => c.type === "listItem") };
    }
    case "bulletList":
      return { type, content: children().filter((c) => c.type === "listItem") };
    case "image": {
      const src = n.attrs?.src;
      if (!isSafeImageSrc(src)) return null;
      const assetId = str(n.attrs?.assetId, 64);
      return {
        type,
        attrs: {
          src,
          alt: str(n.attrs?.alt, 300) ?? "",
          ...(assetId ? { assetId } : {}),
          ...(posInt(n.attrs?.width, 20000) ? { width: n.attrs?.width } : {}),
          ...(posInt(n.attrs?.height, 20000) ? { height: n.attrs?.height } : {}),
          ...(str(n.attrs?.caption, 300) ? { caption: str(n.attrs?.caption, 300) } : {}),
        },
      };
    }
    case "videoEmbed": {
      const provider = n.attrs?.provider;
      const videoId = n.attrs?.videoId;
      if (!isValidVideoId(provider, videoId)) return null;
      return {
        type,
        attrs: {
          provider,
          videoId,
          ...(str(n.attrs?.title, 200) ? { title: str(n.attrs?.title, 200) } : {}),
        },
      };
    }
    default:
      // paragraph, listItem, blockquote
      return { type, content: children() };
  }
}

/**
 * Strict allow-list sanitisation. Accepts anything (unknown JSON from the DB or the client)
 * and never throws. The result contains only allowed nodes, marks and attributes.
 */
export function sanitizeRichText(input: unknown): RichTextDoc {
  if (!input || typeof input !== "object" || (input as RichTextNode).type !== "doc") return emptyDoc();
  const counter: Counter = { n: 0 };
  const content = Array.isArray((input as RichTextNode).content)
    ? ((input as RichTextNode).content as unknown[])
        .map((c) => cleanNode(c, 1, counter))
        .filter((c): c is RichTextNode => !!c)
    : [];
  return { type: "doc", content };
}

/** True when the document has no visible content. */
export function isRichTextEmpty(doc: unknown): boolean {
  return richTextToPlainText(doc).trim() === "" && !collectRichTextMedia(doc).length;
}

/** Plain text (for excerpts, search, meta descriptions). */
export function richTextToPlainText(doc: unknown, maxLength = 100_000): string {
  const out: string[] = [];
  const walk = (n: unknown) => {
    if (!n || typeof n !== "object") return;
    const node = n as RichTextNode;
    if (node.type === "text" && typeof node.text === "string") out.push(node.text);
    if (node.type === "hardBreak") out.push(" ");
    if (Array.isArray(node.content)) {
      for (const c of node.content) walk(c);
      if (node.type !== "text" && node.type !== "doc") out.push(" ");
    }
  };
  walk(doc);
  return out.join("").replace(/\s+/g, " ").trim().slice(0, maxLength);
}

/** Media asset ids + video embeds referenced by a doc (media "where used", previews). */
export function collectRichTextMedia(doc: unknown): { type: "image" | "videoEmbed"; id: string }[] {
  const out: { type: "image" | "videoEmbed"; id: string }[] = [];
  const walk = (n: unknown) => {
    if (!n || typeof n !== "object") return;
    const node = n as RichTextNode;
    if (node.type === "image" && typeof node.attrs?.assetId === "string")
      out.push({ type: "image", id: node.attrs.assetId });
    if (node.type === "videoEmbed" && typeof node.attrs?.videoId === "string")
      out.push({ type: "videoEmbed", id: node.attrs.videoId });
    if (Array.isArray(node.content)) node.content.forEach(walk);
  };
  walk(doc);
  return out;
}

/** Asset ids used by image nodes. */
export function collectRichTextAssetIds(doc: unknown): string[] {
  return [
    ...new Set(
      collectRichTextMedia(doc)
        .filter((m) => m.type === "image")
        .map((m) => m.id),
    ),
  ];
}

/**
 * Zod schema for rich-text INPUT in server actions: structural validation, then strict
 * sanitisation. Use `.nullable()` for optional bodies.
 */
export const richTextInput = richTextDocSchema.transform((doc) => sanitizeRichText(doc));
