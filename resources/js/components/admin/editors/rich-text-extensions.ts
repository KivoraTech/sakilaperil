import { mergeAttributes, Node } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import { SAFE_HREF } from "@/lib/blocks/fields";
import { isSafeImageSrc } from "@/lib/richtext/schema";
import { isValidVideoId, videoThumbnailUrl, type VideoProviderKey } from "@/lib/richtext/video";

/**
 * Tiptap extensions for the CMS editor. The node/mark set matches the server allow-list in
 * lib/richtext/schema.ts exactly (anything else would be stripped on save anyway).
 */

/** Image from the media library. Only parses images WE rendered (data-asset-id), so pasting
 *  HTML from other websites never hot-links external images. */
export const MediaImage = Node.create({
  name: "image",
  group: "block",
  atom: true,
  draggable: true,
  addAttributes() {
    return {
      src: { default: null },
      alt: { default: "" },
      width: { default: null },
      height: { default: null },
      caption: { default: null },
      assetId: {
        default: null,
        parseHTML: (el) => el.getAttribute("data-asset-id"),
        renderHTML: (attrs) => (attrs.assetId ? { "data-asset-id": attrs.assetId } : {}),
      },
    };
  },
  parseHTML() {
    return [
      {
        tag: "img[data-asset-id]",
        getAttrs: (el) => (isSafeImageSrc((el as HTMLElement).getAttribute("src")) ? null : false),
      },
    ];
  },
  renderHTML({ HTMLAttributes }) {
    return ["img", mergeAttributes(HTMLAttributes, { class: "rt-editor-image", loading: "lazy" })];
  },
});

/** YouTube / Vimeo embed stored as { provider, videoId }. The editor shows a thumbnail card
 *  (no third-party iframe inside the admin). */
export const VideoEmbed = Node.create({
  name: "videoEmbed",
  group: "block",
  atom: true,
  draggable: true,
  addAttributes() {
    return {
      provider: {
        default: "youtube",
        parseHTML: (el) => el.getAttribute("data-video-provider"),
        renderHTML: (a) => ({ "data-video-provider": a.provider }),
      },
      videoId: {
        default: null,
        parseHTML: (el) => el.getAttribute("data-video-id"),
        renderHTML: (a) => ({ "data-video-id": a.videoId }),
      },
      title: { default: null, renderHTML: () => ({}) },
    };
  },
  parseHTML() {
    return [
      {
        tag: "div[data-video-id]",
        getAttrs: (el) => {
          const e = el as HTMLElement;
          return isValidVideoId(e.getAttribute("data-video-provider"), e.getAttribute("data-video-id"))
            ? null
            : false;
        },
      },
    ];
  },
  renderHTML({ HTMLAttributes, node }) {
    const provider = node.attrs.provider as VideoProviderKey;
    const thumb = isValidVideoId(provider, node.attrs.videoId)
      ? videoThumbnailUrl(provider, node.attrs.videoId as string)
      : null;
    const label = `${provider === "youtube" ? "YouTube" : "Vimeo"} video · ${node.attrs.videoId}`;
    return [
      "div",
      mergeAttributes(HTMLAttributes, { class: "rt-editor-video", "aria-label": label }),
      ...(thumb ? [["img", { src: thumb, alt: "", class: "rt-editor-video-thumb" }]] : []),
      ["span", { class: "rt-editor-video-label" }, `▶ ${label}`],
    ];
  },
});

export function editorExtensions() {
  return [
    StarterKit.configure({
      heading: { levels: [2, 3, 4] },
      code: false,
      codeBlock: false,
      link: {
        openOnClick: false,
        autolink: true,
        defaultProtocol: "https",
        HTMLAttributes: { rel: "noopener noreferrer nofollow", target: null },
        isAllowedUri: (url) => SAFE_HREF.test(url),
        shouldAutoLink: (url) => /^https:\/\//.test(url) || !/^[a-z]+:/i.test(url),
      },
    }),
    MediaImage,
    VideoEmbed,
  ];
}
