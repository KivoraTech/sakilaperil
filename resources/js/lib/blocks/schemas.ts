import { z } from "zod";
import { richTextDocSchema } from "@/lib/richtext/schema";
import { anchor, baseSettings, entityId, imageRef, link, plainText, requiredText, shortText } from "./fields";

/**
 * Block content/settings schemas — VERSION 1 of every block.
 *
 * Rules (docs/decisions/ADR-003-page-builder.md):
 * - Never make a breaking change to an existing version. To change shape, add `xxxV2`,
 *   bump `version` in the registry and add a migration from v1 → v2.
 * - Additive optional fields are allowed within a version.
 * - No HTML strings anywhere. Emphasis uses the separate `headingAccent` field
 *   (rendered in italic accent colour after `heading`).
 */

const headingFields = {
  kicker: shortText(80).optional(),
  heading: requiredText(160),
  headingAccent: shortText(120).optional(),
};

// ── hero ──────────────────────────────────────────────────────────────
export const heroV1 = {
  content: z.object({
    ...headingFields,
    subheading: plainText(400).optional(),
    image: imageRef,
    primaryCta: link.optional(),
    secondaryCta: link.optional(),
    /** Shows the project's goal/raised progress card (from Project.goalAmount/raisedAmount). */
    highlightProjectId: entityId.optional(),
  }),
  settings: baseSettings.extend({
    height: z.enum(["full", "large", "medium"]).default("full"),
    overlay: z.enum(["dark", "medium", "light"]).default("dark"),
  }),
};

// ── richText ──────────────────────────────────────────────────────────
export const richTextV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: shortText(160).optional(),
    body: richTextDocSchema,
  }),
  settings: baseSettings.extend({
    width: z.enum(["narrow", "normal", "wide"]).default("normal"),
  }),
};

// ── image ─────────────────────────────────────────────────────────────
export const imageV1 = {
  content: z.object({
    image: imageRef,
    caption: shortText(300).optional(),
  }),
  settings: baseSettings.extend({
    width: z.enum(["normal", "wide", "full"]).default("wide"),
  }),
};

// ── imageText ─────────────────────────────────────────────────────────
export const imageTextV1 = {
  content: z.object({
    ...headingFields,
    body: plainText(4000).optional(),
    image: imageRef,
    secondaryImage: imageRef.optional(),
    /** Small circular badge text, e.g. "DAL 2017 · ITALIA ↔ NEPAL". */
    badgeText: shortText(60).optional(),
    primaryCta: link.optional(),
    secondaryCta: link.optional(),
  }),
  settings: baseSettings.extend({
    imagePosition: z.enum(["left", "right"]).default("right"),
  }),
};

// ── quote (parallax band / story) ─────────────────────────────────────
export const quoteV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    quote: requiredText(600),
    quoteAccent: shortText(200).optional(),
    author: shortText(120).optional(),
    role: shortText(160).optional(),
    body: plainText(1200).optional(),
    image: imageRef.optional(),
    imageCaption: shortText(200).optional(),
    cta: link.optional(),
  }),
  settings: baseSettings.extend({
    variant: z.enum(["band", "story"]).default("band"),
  }),
};

// ── cta ───────────────────────────────────────────────────────────────
export const ctaV1 = {
  content: z.object({
    ...headingFields,
    body: plainText(800).optional(),
    primaryCta: link,
    secondaryCta: link.optional(),
    image: imageRef.optional(),
  }),
  settings: baseSettings.extend({
    tone: z.enum(["sindoor", "himalaya", "marigold", "paper"]).default("himalaya"),
  }),
};

// ── gallery ───────────────────────────────────────────────────────────
export const galleryV1 = {
  content: z
    .object({
      kicker: shortText(80).optional(),
      heading: shortText(160).optional(),
      headingAccent: shortText(120).optional(),
      lead: plainText(400).optional(),
      source: z.enum(["album", "images"]).default("images"),
      albumId: entityId.optional(),
      images: z
        .array(imageRef.extend({ caption: shortText(200).optional() }))
        .max(60)
        .default([]),
      limit: z.number().int().min(1).max(60).default(12),
    })
    .superRefine((v, ctx) => {
      if (v.source === "album" && !v.albumId)
        ctx.addIssue({ code: "custom", path: ["albumId"], message: "Choose an album" });
      if (v.source === "images" && v.images.length === 0)
        ctx.addIssue({ code: "custom", path: ["images"], message: "Add at least one image" });
    }),
  settings: baseSettings.extend({
    layout: z.enum(["masonry", "grid"]).default("masonry"),
  }),
};

// ── services (= projects highlight) ───────────────────────────────────
export const servicesV1 = {
  content: z.object({
    ...headingFields,
    lead: plainText(400).optional(),
    source: z.enum(["featured", "latest", "manual"]).default("featured"),
    projectIds: z.array(entityId).max(12).default([]),
    limit: z.number().int().min(1).max(12).default(4),
    endCard: z
      .object({
        heading: requiredText(160),
        body: plainText(400).optional(),
        cta: link,
      })
      .optional(),
  }),
  settings: baseSettings.extend({
    layout: z.enum(["carousel", "grid"]).default("carousel"),
  }),
};

// ── impactStats ───────────────────────────────────────────────────────
export const impactStatsV1 = {
  content: z.object({
    heading: shortText(160).optional(),
    /** "settings" = use SiteSettings.impactStats (edited once, reused everywhere). */
    source: z.enum(["settings", "custom"]).default("settings"),
    items: z
      .array(
        z.object({
          value: z.number().min(0).max(1_000_000_000),
          suffix: shortText(8).optional(),
          label: requiredText(120),
          progress: z.number().min(0).max(1).optional(),
        }),
      )
      .max(8)
      .default([]),
  }),
  settings: baseSettings,
};

// ── testimonials ──────────────────────────────────────────────────────
export const testimonialsV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: shortText(160).optional(),
    items: z
      .array(
        z.object({
          quote: requiredText(600),
          name: requiredText(80),
          role: shortText(120).optional(),
          image: imageRef.optional(),
        }),
      )
      .min(1)
      .max(12),
  }),
  settings: baseSettings,
};

// ── faq ───────────────────────────────────────────────────────────────
export const faqV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: shortText(160).optional(),
    lead: plainText(400).optional(),
    items: z
      .array(z.object({ question: requiredText(200), answer: requiredText(3000) }))
      .min(1)
      .max(40),
  }),
  settings: baseSettings,
};

// ── newsList ──────────────────────────────────────────────────────────
export const newsListV1 = {
  content: z.object({
    ...headingFields,
    limit: z.number().int().min(1).max(12).default(3),
    categoryId: entityId.optional(),
    showAllLink: z.boolean().default(true),
  }),
  settings: baseSettings,
};

// ── donateCta ─────────────────────────────────────────────────────────
export const donateCtaV1 = {
  content: z.object({
    ...headingFields,
    lead: plainText(600).optional(),
    /** Gift examples: "€10 = notebooks for a year". Amount in whole euros. */
    gifts: z
      .array(
        z.object({
          amount: z.number().int().min(1).max(100_000),
          description: requiredText(200),
          image: imageRef.optional(),
        }),
      )
      .max(4)
      .default([]),
    reasons: z.array(requiredText(160)).max(6).default([]),
    trustStats: z
      .array(z.object({ value: requiredText(20), label: requiredText(60) }))
      .max(3)
      .default([]),
    sideImage: imageRef.optional(),
    /** Embed the donation widget (card / eSewa-Khalti / bank transfer tabs). */
    showWidget: z.boolean().default(true),
    projectId: entityId.optional(),
  }),
  settings: baseSettings,
};

// ── team ──────────────────────────────────────────────────────────────
export const teamV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: requiredText(160),
    lead: plainText(400).optional(),
    members: z
      .array(
        z.object({
          name: requiredText(80),
          role: shortText(120).optional(),
          bio: plainText(600).optional(),
          image: imageRef.optional(),
        }),
      )
      .max(40)
      .default([]),
  }),
  settings: baseSettings,
};

// ── partners ──────────────────────────────────────────────────────────
export const partnersV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: requiredText(160),
    body: plainText(800).optional(),
    partners: z
      .array(
        z.object({
          name: requiredText(120),
          url: z
            .string()
            .trim()
            .regex(/^https:\/\//, "Use an https:// URL")
            .max(500)
            .optional(),
          logo: imageRef.optional(),
        }),
      )
      .max(40)
      .default([]),
    cta: link.optional(),
  }),
  settings: baseSettings,
};

// ── timeline ──────────────────────────────────────────────────────────
export const timelineV1 = {
  content: z.object({
    ...headingFields,
    items: z
      .array(
        z.object({
          year: requiredText(12),
          title: requiredText(120),
          body: plainText(600).optional(),
          image: imageRef.optional(),
        }),
      )
      .min(1)
      .max(20),
  }),
  settings: baseSettings,
};

// ── newsletter ────────────────────────────────────────────────────────
export const newsletterV1 = {
  content: z.object({
    ...headingFields,
    body: plainText(400).optional(),
    note: shortText(200).optional(),
  }),
  settings: baseSettings.extend({
    tone: z.enum(["himalaya", "deep", "paper"]).default("himalaya"),
  }),
};

// ── contact ───────────────────────────────────────────────────────────
export const contactV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: requiredText(160),
    lead: plainText(400).optional(),
    showForm: z.boolean().default(true),
    showDetails: z.boolean().default(true),
    showMap: z.boolean().default(true),
  }),
  settings: baseSettings,
};

// ── spacer ────────────────────────────────────────────────────────────
export const spacerV1 = {
  content: z.object({}),
  settings: baseSettings.extend({
    size: z.enum(["sm", "md", "lg", "xl"]).default("md"),
    divider: z.boolean().default(false),
  }),
};

// ── reportsList (Transparency) ────────────────────────────────────────
export const reportsListV1 = {
  content: z.object({
    kicker: shortText(80).optional(),
    heading: requiredText(160),
    lead: plainText(600).optional(),
    types: z
      .array(z.enum(["ANNUAL_REPORT", "FINANCIAL_STATEMENT", "SOCIAL_REPORT", "OTHER"]))
      .max(4)
      .default([]),
  }),
  settings: baseSettings,
};

// ── involveForms (Get Involved: volunteer / membership / partner) ─────
export const involveFormsV1 = {
  content: z.object({
    ...headingFields,
    lead: plainText(600).optional(),
    forms: z
      .array(z.enum(["volunteer", "membership", "partner"]))
      .min(1)
      .max(3)
      .default(["volunteer", "membership", "partner"]),
  }),
  settings: baseSettings,
};

// ── marquee (added 2026-09-29, additive) ──────────────────────────────
/** Big words scrolling sideways (demo strip). Pausable; static wrapped row when motion is off. */
export const marqueeV1 = {
  content: z.object({
    items: z
      .array(z.object({ text: requiredText(40) }))
      .min(2)
      .max(12),
  }),
  settings: z.object({
    anchor,
    background: z.enum(["deep", "himalaya", "paper", "cream"]).default("deep"),
    /** `alternate` = every second word in outlined italic (demo look); `solid` = all filled. */
    style: z.enum(["alternate", "solid"]).default("alternate"),
    speed: z.enum(["slow", "normal", "fast"]).default("normal"),
  }),
};
