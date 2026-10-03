import type { z } from "zod";
import * as s from "./schemas";

/**
 * Block registry — the single source of truth for which section types exist, their
 * CURRENT schema version and how to migrate stored content from older versions.
 *
 * Adding a block: add schemas in ./schemas.ts, register here, add a renderer in
 * components/site/blocks/<Type>.tsx + components/site/blocks/index.ts, and an editor
 * form in components/admin/blocks/. A registry test enforces that every block has a
 * renderer.
 */

export type BlockCategory = "hero" | "content" | "media" | "collections" | "engagement" | "layout";

type RawBlock = { content: unknown; settings: unknown };

export type BlockDefinition<C extends z.ZodType = z.ZodType, S extends z.ZodType = z.ZodType> = {
  label: string;
  description: string;
  category: BlockCategory;
  /** Current schema version. Stored sections with a lower version are migrated on read. */
  version: number;
  content: C;
  settings: S;
  /** Starting values for "Add block". May be incomplete (e.g. no image picked yet). */
  defaults: () => { content: z.input<C>; settings: z.input<S> };
  /**
   * Upgrade raw stored data from `fromVersion` to `fromVersion + 1`.
   * Required once `version` > 1. Pure; must not throw on well-formed old data.
   */
  migrate?: (fromVersion: number, raw: RawBlock) => RawBlock;
  /** Only one per page (e.g. hero). Enforced by the builder UI. */
  singleton?: boolean;
};

/** Curried so the schemas fix C/S before `defaults` is type-checked (keeps literal types). */
function defineBlock<C extends z.ZodType, S extends z.ZodType>(schemas: { content: C; settings: S }) {
  return (meta: Omit<BlockDefinition<C, S>, "content" | "settings">): BlockDefinition<C, S> => ({
    ...schemas,
    ...meta,
  });
}

export const blockRegistry = {
  hero: defineBlock(s.heroV1)({
    label: "Hero",
    description: "Full-width opening image with headline and buttons",
    category: "hero",
    version: 1,
    singleton: true,
    defaults: () => ({
      content: { heading: "Titolo", image: { assetId: "" } },
      settings: { height: "full", overlay: "dark" },
    }),
  }),
  richText: defineBlock(s.richTextV1)({
    label: "Rich text",
    description: "Formatted text with headings, lists and links",
    category: "content",
    version: 1,
    defaults: () => ({ content: { body: { type: "doc", content: [] } }, settings: {} }),
  }),
  image: defineBlock(s.imageV1)({
    label: "Image",
    description: "A single image with optional caption",
    category: "media",
    version: 1,
    defaults: () => ({ content: { image: { assetId: "" } }, settings: {} }),
  }),
  imageText: defineBlock(s.imageTextV1)({
    label: "Image + text",
    description: "Text beside one or two photos",
    category: "content",
    version: 1,
    defaults: () => ({ content: { heading: "Titolo", image: { assetId: "" } }, settings: {} }),
  }),
  quote: defineBlock(s.quoteV1)({
    label: "Quote / story",
    description: "A large quote over a photo, or a personal story",
    category: "content",
    version: 1,
    defaults: () => ({ content: { quote: "Citazione" }, settings: { variant: "band" } }),
  }),
  cta: defineBlock(s.ctaV1)({
    label: "Call to action",
    description: "Highlighted message with one or two buttons",
    category: "engagement",
    version: 1,
    defaults: () => ({
      content: { heading: "Titolo", primaryCta: { label: "Dona ora", href: "#dona" } },
      settings: {},
    }),
  }),
  gallery: defineBlock(s.galleryV1)({
    label: "Gallery",
    description: "Photo grid from an album or hand-picked images",
    category: "media",
    version: 1,
    defaults: () => ({ content: { source: "album", images: [] }, settings: {} }),
  }),
  services: defineBlock(s.servicesV1)({
    label: "Projects highlight",
    description: "Cards for featured, latest or chosen projects",
    category: "collections",
    version: 1,
    defaults: () => ({ content: { heading: "I nostri progetti" }, settings: {} }),
  }),
  impactStats: defineBlock(s.impactStatsV1)({
    label: "Impact numbers",
    description: "Animated key figures (families supported, projects…)",
    category: "content",
    version: 1,
    defaults: () => ({ content: { source: "settings", items: [] }, settings: {} }),
  }),
  testimonials: defineBlock(s.testimonialsV1)({
    label: "Testimonials",
    description: "Quotes from donors, volunteers and partners",
    category: "content",
    version: 1,
    defaults: () => ({ content: { items: [{ quote: "…", name: "Nome" }] }, settings: {} }),
  }),
  faq: defineBlock(s.faqV1)({
    label: "FAQ",
    description: "Questions and answers (accordion)",
    category: "content",
    version: 1,
    defaults: () => ({ content: { items: [{ question: "Domanda?", answer: "Risposta." }] }, settings: {} }),
  }),
  newsList: defineBlock(s.newsListV1)({
    label: "Latest news",
    description: "The most recent news & stories",
    category: "collections",
    version: 1,
    defaults: () => ({ content: { heading: "Ultime notizie" }, settings: {} }),
  }),
  donateCta: defineBlock(s.donateCtaV1)({
    label: "Donate",
    description: "Gift examples, reasons to give and the donation widget",
    category: "engagement",
    version: 1,
    defaults: () => ({ content: { heading: "Dona ora" }, settings: { anchor: "dona" } }),
  }),
  team: defineBlock(s.teamV1)({
    label: "Team",
    description: "The people behind the association",
    category: "content",
    version: 1,
    defaults: () => ({ content: { heading: "Chi siamo", members: [] }, settings: {} }),
  }),
  partners: defineBlock(s.partnersV1)({
    label: "Partners",
    description: "Partner organisations and businesses",
    category: "engagement",
    version: 1,
    defaults: () => ({ content: { heading: "Amici e partner", partners: [] }, settings: {} }),
  }),
  timeline: defineBlock(s.timelineV1)({
    label: "Timeline",
    description: "Milestones of the association's journey",
    category: "content",
    version: 1,
    defaults: () => ({
      content: { heading: "Il nostro cammino", items: [{ year: "2026", title: "Oggi" }] },
      settings: {},
    }),
  }),
  newsletter: defineBlock(s.newsletterV1)({
    label: "Newsletter sign-up",
    description: "Email sign-up box",
    category: "engagement",
    version: 1,
    defaults: () => ({ content: { heading: "Resta in contatto" }, settings: {} }),
  }),
  contact: defineBlock(s.contactV1)({
    label: "Contact",
    description: "Contact form, details and map",
    category: "engagement",
    version: 1,
    defaults: () => ({ content: { heading: "Contatti" }, settings: {} }),
  }),
  reportsList: defineBlock(s.reportsListV1)({
    label: "Reports list",
    description: "Downloadable annual reports and financial statements",
    category: "collections",
    version: 1,
    defaults: () => ({ content: { heading: "Bilanci e rapporti" }, settings: {} }),
  }),
  involveForms: defineBlock(s.involveFormsV1)({
    label: "Get involved forms",
    description: "Volunteer, membership and partner sign-up forms",
    category: "engagement",
    version: 1,
    defaults: () => ({ content: { heading: "Partecipa" }, settings: {} }),
  }),
  spacer: defineBlock(s.spacerV1)({
    label: "Spacer / divider",
    description: "Vertical space, optionally with a line",
    category: "layout",
    version: 1,
    defaults: () => ({ content: {}, settings: {} }),
  }),
  marquee: defineBlock(s.marqueeV1)({
    label: "Scrolling words",
    description: "A band of big words that slowly scrolls sideways (visitors can pause it)",
    category: "layout",
    version: 1,
    defaults: () => ({
      content: {
        items: ["Istruzione", "Salute", "Casa", "Dignità", "Comunità", "Speranza"].map((text) => ({ text })),
      },
      settings: {},
    }),
  }),
} as const;

export type BlockRegistry = typeof blockRegistry;
export type BlockType = keyof BlockRegistry;
export const blockTypes = Object.keys(blockRegistry) as BlockType[];

export type BlockContent<T extends BlockType> = z.output<BlockRegistry[T]["content"]>;
export type BlockSettings<T extends BlockType> = z.output<BlockRegistry[T]["settings"]>;
export type BlockContentInput<T extends BlockType> = z.input<BlockRegistry[T]["content"]>;
export type BlockSettingsInput<T extends BlockType> = z.input<BlockRegistry[T]["settings"]>;

/** A validated section ready to render, discriminated by `type`. */
export type ParsedBlock = {
  [T in BlockType]: {
    id: string;
    type: T;
    content: BlockContent<T>;
    settings: BlockSettings<T>;
  };
}[BlockType];

export function isBlockType(value: unknown): value is BlockType {
  return typeof value === "string" && Object.hasOwn(blockRegistry, value);
}

export function getBlockDefinition(type: BlockType): BlockDefinition {
  return blockRegistry[type] as unknown as BlockDefinition;
}
